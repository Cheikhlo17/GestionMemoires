<?php

namespace App\Services;

use App\Models\Department;
use App\Models\DefenseSchedule;
use App\Models\PreDefenseHistory;
use App\Models\PreDefensePassage;
use App\Models\PreDefenseSession;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\Thesis;
use App\Models\User;
use App\Services\Interfaces\PreDefenseServiceInterface;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PreDefenseService implements PreDefenseServiceInterface
{
    public function list(User $viewer, array $filters): LengthAwarePaginator
    {
        Gate::forUser($viewer)->authorize('viewAny', PreDefenseSession::class);
        $query = PreDefenseSession::query()->with([
            'department',
            'responsible',
            'passages.student.user',
            'passages.thesis.student.user',
            'passages.thesis.supervisor.user',
            'history.changedBy',
        ]);
        $role = $viewer->role?->slug;

        if ($role === 'head-of-department') {
            $query->whereHas('department', fn ($departments) => $departments->where('head_of_department_id', $viewer->id));
        } elseif ($role === 'student') {
            $query->whereHas('passages.student', fn ($students) => $students->where('user_id', $viewer->id));
        } elseif ($role === 'supervisor') {
            $query->where(fn ($sessions) => $sessions->where('responsible_user_id', $viewer->id)
                ->orWhereHas('passages.thesis.supervisor', fn ($supervisors) => $supervisors->where('user_id', $viewer->id)));
        }

        if (!empty($filters['department_id']) && $role === 'administrator') {
            $query->where('department_id', $filters['department_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('scheduled_at')->paginate($filters['per_page'] ?? 15);
    }

    public function createSession(array $data, User $actor): PreDefenseSession
    {
        Gate::forUser($actor)->authorize('create', PreDefenseSession::class);

        return DB::transaction(function () use ($data, $actor) {
            $department = Department::query()->lockForUpdate()->findOrFail($data['department_id']);
            $responsible = User::query()->whereKey($data['responsible_user_id'])->where('is_active', true)->first();

            if (!$responsible || !$this->isResponsibleInDepartment($responsible, $department)) {
                throw ValidationException::withMessages([
                    'responsible_user_id' => ['The responsible person must be active and authorized in this department.'],
                ]);
            }

            if ($actor->role?->slug === 'head-of-department'
                && (int) $department->head_of_department_id !== (int) $actor->id) {
                throw new \Illuminate\Auth\Access\AuthorizationException();
            }
            if ($actor->role?->slug === 'supervisor'
                && !Supervisor::where('user_id', $actor->id)->where('department_id', $department->id)->exists()) {
                throw new \Illuminate\Auth\Access\AuthorizationException();
            }

            $start = Carbon::parse($data['scheduled_at']);
            $duration = $data['duration_minutes'] ?? 240;
            $end = $start->copy()->addMinutes($duration);
            $this->assertNoLocationConflict($data['location'], $start, $end);

            $session = PreDefenseSession::create([
                ...$data,
                'created_by' => $actor->id,
                'duration_minutes' => $duration,
                'status' => PreDefenseSession::STATUS_SCHEDULED,
            ]);
            $this->recordHistory($session, null, 'session_created', $actor, null, $session->only([
                'department_id', 'responsible_user_id', 'location', 'scheduled_at', 'duration_minutes',
            ]));

            return $session->load([
                'department',
                'responsible',
                'passages.student.user',
                'passages.thesis.student.user',
                'history.changedBy',
            ]);
        });
    }

    public function updateSession(PreDefenseSession $session, array $data, User $actor): PreDefenseSession
    {
        Gate::forUser($actor)->authorize('manage', $session);

        return DB::transaction(function () use ($session, $data, $actor) {
            $session = PreDefenseSession::query()->lockForUpdate()->findOrFail($session->id);
            Gate::forUser($actor)->authorize('manage', $session);

            if ($session->status !== PreDefenseSession::STATUS_SCHEDULED) {
                throw ValidationException::withMessages(['status' => ['Only a scheduled session can be changed.']]);
            }

            $changes = $data;
            $location = $data['location'] ?? $session->location;
            $start = Carbon::parse($data['scheduled_at'] ?? $session->scheduled_at);
            $duration = $data['duration_minutes'] ?? $session->duration_minutes;
            $end = $start->copy()->addMinutes($duration);

            if (isset($data['responsible_user_id'])) {
                $department = Department::findOrFail($session->department_id);
                $responsible = User::query()->whereKey($data['responsible_user_id'])->where('is_active', true)->first();
                if (!$responsible || !$this->isResponsibleInDepartment($responsible, $department)) {
                    throw ValidationException::withMessages([
                        'responsible_user_id' => ['The responsible person must be active and authorized in this department.'],
                    ]);
                }
            }

            if (isset($data['location']) || isset($data['scheduled_at']) || isset($data['duration_minutes'])) {
                $this->assertNoLocationConflict($location, $start, $end, $session->id);
                $passages = $session->passages()->get();
                foreach ($passages as $passage) {
                    if ($passage->scheduled_at < $start || $passage->ends_at > $end) {
                        throw ValidationException::withMessages([
                            'scheduled_at' => ['The updated session schedule must contain every existing passage.'],
                        ]);
                    }
                    Student::whereKey($passage->student_id)->lockForUpdate()->first();
                    $this->assertNoStudentConflict(
                        $passage->student_id,
                        $passage->scheduled_at,
                        $passage->ends_at,
                        $passage->id
                    );
                }
            }

            $oldValues = $session->only(array_keys($changes));
            $session->update($changes);
            $this->recordHistory($session, null, 'session_updated', $actor, $oldValues, $session->only(array_keys($changes)));

            return $session->fresh([
                'department',
                'responsible',
                'passages.student.user',
                'passages.thesis.student.user',
                'history.changedBy',
            ]);
        });
    }

    public function addPassage(PreDefenseSession $session, array $data, User $actor): PreDefensePassage
    {
        Gate::forUser($actor)->authorize('manage', $session);

        return DB::transaction(function () use ($session, $data, $actor) {
            $session = PreDefenseSession::query()->lockForUpdate()->findOrFail($session->id);
            Gate::forUser($actor)->authorize('manage', $session);

            if ($session->status !== PreDefenseSession::STATUS_SCHEDULED) {
                throw ValidationException::withMessages(['status' => ['Passages can only be added to a scheduled session.']]);
            }

            $thesis = Thesis::query()->with('student')->lockForUpdate()->findOrFail($data['thesis_id']);
            if ((int) $thesis->department_id !== (int) $session->department_id) {
                throw ValidationException::withMessages([
                    'thesis_id' => ['The thesis must belong to the session department.'],
                ]);
            }

            $start = Carbon::parse($data['scheduled_at']);
            $duration = $data['duration_minutes'] ?? 15;
            $end = $start->copy()->addMinutes($duration);
            if ($start < $session->scheduled_at || $end > $session->ends_at) {
                throw ValidationException::withMessages([
                    'scheduled_at' => ['The passage must fit within the session schedule.'],
                ]);
            }

            if ($session->passages()->where('position', $data['position'])->exists()) {
                throw ValidationException::withMessages([
                    'position' => ['This passage order is already used in the session.'],
                ]);
            }

            $student = $thesis->student;
            User::whereKey($student->user_id)->lockForUpdate()->first();
            $this->assertNoStudentConflict($student->id, $start, $end);

            $passage = $session->passages()->create([
                ...$data,
                'student_id' => $student->id,
                'duration_minutes' => $duration,
                'outcome' => PreDefensePassage::OUTCOME_PENDING,
            ]);
            $this->recordHistory(
                $session,
                $passage->id,
                'passage_created',
                $actor,
                null,
                $passage->only(['thesis_id', 'student_id', 'position', 'scheduled_at', 'duration_minutes'])
            );

            return $passage->load(['student.user', 'thesis.student.user', 'thesis.supervisor.user']);
        });
    }

    public function updatePassage(
        PreDefenseSession $session,
        PreDefensePassage $passage,
        array $data,
        User $actor
    ): PreDefensePassage {
        Gate::forUser($actor)->authorize('manage', $session);

        return DB::transaction(function () use ($session, $passage, $data, $actor) {
            $session = PreDefenseSession::query()->lockForUpdate()->findOrFail($session->id);
            Gate::forUser($actor)->authorize('manage', $session);
            $passage = PreDefensePassage::query()
                ->where('pre_defense_session_id', $session->id)
                ->lockForUpdate()
                ->findOrFail($passage->id);
            if ($session->status !== PreDefenseSession::STATUS_SCHEDULED) {
                throw ValidationException::withMessages(['status' => ['Only scheduled session passages can be changed.']]);
            }

            $oldValues = $passage->only(array_keys($data));
            $start = Carbon::parse($data['scheduled_at'] ?? $passage->scheduled_at);
            $duration = $data['duration_minutes'] ?? $passage->duration_minutes;
            $end = $start->copy()->addMinutes($duration);
            if ($start < $session->scheduled_at || $end > $session->ends_at) {
                throw ValidationException::withMessages([
                    'scheduled_at' => ['The passage must fit within the session schedule.'],
                ]);
            }

            if (isset($data['position'])
                && $session->passages()->where('position', $data['position'])->where('id', '!=', $passage->id)->exists()) {
                throw ValidationException::withMessages([
                    'position' => ['This passage order is already used in the session.'],
                ]);
            }

            if (isset($data['scheduled_at']) || isset($data['duration_minutes'])) {
                Student::whereKey($passage->student_id)->lockForUpdate()->first();
                $this->assertNoStudentConflict($passage->student_id, $start, $end, $passage->id);
            }

            $passage->update($data);
            $this->recordHistory(
                $session,
                $passage->id,
                'passage_updated',
                $actor,
                $oldValues,
                $passage->only(array_keys($data))
            );

            return $passage->fresh(['thesis.student.user', 'thesis.supervisor.user']);
        });
    }

    public function updateSessionStatus(PreDefenseSession $session, string $status, User $actor): PreDefenseSession
    {
        Gate::forUser($actor)->authorize('manage', $session);

        if (!in_array($status, [PreDefenseSession::STATUS_COMPLETED, PreDefenseSession::STATUS_CANCELLED], true)) {
            throw ValidationException::withMessages(['status' => ['The requested session status is not allowed.']]);
        }

        return DB::transaction(function () use ($session, $status, $actor) {
            $session = PreDefenseSession::query()->lockForUpdate()->findOrFail($session->id);
            Gate::forUser($actor)->authorize('manage', $session);

            if ($session->status !== PreDefenseSession::STATUS_SCHEDULED) {
                throw ValidationException::withMessages(['status' => ['Only a scheduled session can be closed.']]);
            }
            if ($status === PreDefenseSession::STATUS_COMPLETED
                && $session->passages()->where('outcome', PreDefensePassage::OUTCOME_PENDING)->exists()) {
                throw ValidationException::withMessages([
                    'status' => ['Every passage needs a recorded outcome before completing the session.'],
                ]);
            }

            $previous = $session->status;
            $session->update([
                'status' => $status,
                'completed_at' => $status === PreDefenseSession::STATUS_COMPLETED ? now() : null,
            ]);
            $this->recordHistory($session, null, 'session_status_changed', $actor, ['status' => $previous], ['status' => $status]);

            return $session->fresh([
                'department',
                'responsible',
                'passages.student.user',
                'passages.thesis.student.user',
                'history.changedBy',
            ]);
        });
    }

    private function isResponsibleInDepartment(User $user, Department $department): bool
    {
        return $user->role?->slug === 'administrator'
            || ($user->role?->slug === 'head-of-department' && (int) $department->head_of_department_id === (int) $user->id)
            || ($user->role?->slug === 'supervisor'
                && Supervisor::where('user_id', $user->id)->where('department_id', $department->id)->exists());
    }

    private function assertNoLocationConflict(
        string $location,
        Carbon $start,
        Carbon $end,
        ?int $exceptSessionId = null
    ): void {
        $sessions = PreDefenseSession::query()
            ->where('location', $location)
            ->where('status', PreDefenseSession::STATUS_SCHEDULED)
            ->when($exceptSessionId, fn ($query) => $query->where('id', '!=', $exceptSessionId))
            ->where('scheduled_at', '<', $end)
            ->get(['scheduled_at', 'duration_minutes']);

        if ($sessions->contains(fn (PreDefenseSession $candidate) => $candidate->ends_at > $start)) {
            throw ValidationException::withMessages([
                'location' => ['The location is already reserved for this time.'],
            ]);
        }
    }

    private function assertNoStudentConflict(
        int $studentId,
        Carbon $start,
        Carbon $end,
        ?int $exceptPassageId = null
    ): void {
        $passages = PreDefensePassage::query()
            ->where('student_id', $studentId)
            ->whereHas('session', fn ($sessions) => $sessions->where('status', PreDefenseSession::STATUS_SCHEDULED))
            ->when($exceptPassageId, fn ($query) => $query->where('id', '!=', $exceptPassageId))
            ->where('scheduled_at', '<', $end)
            ->get(['scheduled_at', 'duration_minutes']);

        if ($passages->contains(fn (PreDefensePassage $candidate) => $candidate->ends_at > $start)) {
            throw ValidationException::withMessages([
                'scheduled_at' => ['The student already has a pre-defense passage during this time.'],
            ]);
        }

        $officialDefenses = DefenseSchedule::query()
            ->where('status', DefenseSchedule::STATUS_SCHEDULED)
            ->whereHas('thesis', fn ($theses) => $theses->where('student_id', $studentId))
            ->where('scheduled_at', '<', $end)
            ->get(['scheduled_at', 'duration_minutes']);

        if ($officialDefenses->contains(fn ($defense) => $defense->ends_at > $start)) {
            throw ValidationException::withMessages([
                'scheduled_at' => ['The student has an official defense during this time.'],
            ]);
        }
    }

    private function recordHistory(
        PreDefenseSession $session,
        ?int $passageId,
        string $event,
        User $actor,
        ?array $oldValues,
        ?array $newValues
    ): void {
        PreDefenseHistory::create([
            'pre_defense_session_id' => $session->id,
            'pre_defense_passage_id' => $passageId,
            'changed_by' => $actor->id,
            'event' => $event,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }
}
