<?php

namespace App\Services;

use App\Models\Student;
use App\Models\ThesisSubject;
use App\Models\ThesisSubjectHistory;
use App\Models\User;
use App\Services\Interfaces\ThesisSubjectServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ThesisSubjectService implements ThesisSubjectServiceInterface
{
    public function list(User $viewer, array $filters): LengthAwarePaginator
    {
        Gate::forUser($viewer)->authorize('viewAny', ThesisSubject::class);

        $query = ThesisSubject::query()->with(['department', 'student.user', 'reviewer', 'history.changedBy']);
        $role = $viewer->role?->slug;

        if ($role === 'student') {
            $student = Student::where('user_id', $viewer->id)->first();

            if (!$student) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function ($subjects) use ($student) {
                    $subjects->where('student_id', $student->id)
                        ->orWhere(function ($published) use ($student) {
                            $published->where('department_id', $student->department_id)
                                ->where('status', ThesisSubject::STATUS_PUBLISHED)
                                ->where('is_published', true)
                                ->whereNull('student_id');
                        });
                });
            }
        } elseif ($role === 'head-of-department') {
            $query->whereHas('department', fn ($departments) => $departments->where('head_of_department_id', $viewer->id));
        } elseif ($role === 'supervisor') {
            $query->whereHas('thesis', fn ($theses) => $theses->whereHas('supervisor', fn ($supervisors) => $supervisors->where('user_id', $viewer->id)));
        }

        if (!empty($filters['department_id']) && $role === 'administrator') {
            $query->where('department_id', $filters['department_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderByDesc('created_at')->paginate($filters['per_page'] ?? 15);
    }

    public function createProposal(array $data, User $studentUser): ThesisSubject
    {
        Gate::forUser($studentUser)->authorize('create', ThesisSubject::class);
        $student = Student::where('user_id', $studentUser->id)->firstOrFail();

        return DB::transaction(function () use ($data, $studentUser, $student) {
            $subject = ThesisSubject::create([
                ...$data,
                'student_id' => $student->id,
                'department_id' => $student->department_id,
                'created_by' => $studentUser->id,
                'status' => ThesisSubject::STATUS_DRAFT,
            ]);

            $this->recordHistory($subject, null, ThesisSubject::STATUS_DRAFT, $studentUser, 'Proposal created.');

            return $subject->load(['department', 'student.user', 'history.changedBy']);
        });
    }

    public function updateProposal(ThesisSubject $subject, array $data, User $studentUser): ThesisSubject
    {
        Gate::forUser($studentUser)->authorize('update', $subject);
        $subject->update($data);

        return $subject->fresh(['department', 'student.user', 'history.changedBy']);
    }

    public function publish(array $data, User $publisher): ThesisSubject
    {
        Gate::forUser($publisher)->authorize('publish', [ThesisSubject::class, (int) $data['department_id']]);

        return DB::transaction(function () use ($data, $publisher) {
            $subject = ThesisSubject::create([
                ...$data,
                'student_id' => null,
                'created_by' => $publisher->id,
                'reviewed_by' => $publisher->id,
                'status' => ThesisSubject::STATUS_PUBLISHED,
                'is_published' => true,
                'published_at' => now(),
            ]);

            $this->recordHistory($subject, null, ThesisSubject::STATUS_PUBLISHED, $publisher, 'Published by the department.');

            return $subject->load(['department', 'reviewer', 'history.changedBy']);
        });
    }

    public function submit(ThesisSubject $subject, User $student): ThesisSubject
    {
        Gate::forUser($student)->authorize('submit', $subject);

        return DB::transaction(function () use ($subject, $student) {
            $subject = ThesisSubject::query()->lockForUpdate()->findOrFail($subject->id);
            Gate::forUser($student)->authorize('submit', $subject);

            if (!in_array($subject->status, [ThesisSubject::STATUS_DRAFT, ThesisSubject::STATUS_REJECTED], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Only a draft or rejected proposal can be submitted.'],
                ]);
            }

            $this->transition($subject, ThesisSubject::STATUS_SUBMITTED, $student);
            $subject->decision_reason = null;
            $subject->reviewed_by = null;
            $subject->save();

            return $subject->fresh(['department', 'student.user', 'history.changedBy']);
        });
    }

    public function select(ThesisSubject $subject, User $student): ThesisSubject
    {
        Gate::forUser($student)->authorize('select', $subject);

        return DB::transaction(function () use ($subject, $student) {
            $subject = ThesisSubject::query()->lockForUpdate()->findOrFail($subject->id);
            Gate::forUser($student)->authorize('select', $subject);
            $studentProfile = Student::where('user_id', $student->id)->firstOrFail();

            $subject->student_id = $studentProfile->id;
            $subject->reviewed_by = null;
            $this->transition($subject, ThesisSubject::STATUS_ACCEPTED, $student, 'Published topic selected by student.');

            return $subject->fresh(['department', 'student.user', 'history.changedBy']);
        });
    }

    public function review(ThesisSubject $subject, string $status, User $reviewer, ?string $remarks = null): ThesisSubject
    {
        Gate::forUser($reviewer)->authorize('review', $subject);

        if (!in_array($status, [
            ThesisSubject::STATUS_UNDER_REVIEW,
            ThesisSubject::STATUS_ACCEPTED,
            ThesisSubject::STATUS_REJECTED,
        ], true)) {
            throw ValidationException::withMessages(['status' => ['The requested subject decision is not allowed.']]);
        }

        return DB::transaction(function () use ($subject, $status, $reviewer, $remarks) {
            $subject = ThesisSubject::query()->lockForUpdate()->findOrFail($subject->id);
            Gate::forUser($reviewer)->authorize('review', $subject);

            if (!in_array($subject->status, [ThesisSubject::STATUS_SUBMITTED, ThesisSubject::STATUS_UNDER_REVIEW], true)) {
                throw ValidationException::withMessages(['status' => ['Only submitted proposals can be reviewed.']]);
            }

            $this->transition($subject, $status, $reviewer, $remarks);
            $subject->reviewed_by = $reviewer->id;
            $subject->decision_reason = $status === ThesisSubject::STATUS_REJECTED ? $remarks : null;
            $subject->save();

            return $subject->fresh(['department', 'student.user', 'reviewer', 'history.changedBy']);
        });
    }

    private function transition(ThesisSubject $subject, string $status, User $actor, ?string $remarks = null): void
    {
        $previous = $subject->status;
        $subject->status = $status;
        $subject->save();

        $this->recordHistory($subject, $previous, $status, $actor, $remarks);
    }

    private function recordHistory(
        ThesisSubject $subject,
        ?string $fromStatus,
        string $toStatus,
        User $actor,
        ?string $remarks
    ): void {
        ThesisSubjectHistory::create([
            'thesis_subject_id' => $subject->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'changed_by' => $actor->id,
            'remarks' => $remarks,
        ]);
    }
}
