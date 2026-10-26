<?php

namespace App\Services;

use App\Events\ThesisCommented;
use App\Events\ThesisStatusChanged;
use App\Events\ThesisSubmitted;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Comment;
use App\Models\Thesis;
use App\Models\ThesisSubject;
use App\Models\ThesisStatusHistory;
use App\Models\ThesisVersion;
use App\Models\Supervisor;
use App\Models\User;
use App\Repositories\Interfaces\ThesisRepositoryInterface;
use App\Services\Interfaces\ThesisServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class ThesisService implements ThesisServiceInterface
{
    /**
     * Allowed status transitions: current status => list of statuses it may move to.
     */
    private const TRANSITIONS = [
        Thesis::STATUS_DRAFT => [Thesis::STATUS_SUBMITTED],
        Thesis::STATUS_SUBMITTED => [Thesis::STATUS_UNDER_REVIEW],
        Thesis::STATUS_UNDER_REVIEW => [
            Thesis::STATUS_REVISION_REQUIRED,
            Thesis::STATUS_APPROVED,
            Thesis::STATUS_REJECTED,
        ],
        Thesis::STATUS_REVISION_REQUIRED => [Thesis::STATUS_SUBMITTED],
        Thesis::STATUS_APPROVED => [],
        Thesis::STATUS_REJECTED => [],
        Thesis::STATUS_ARCHIVED => [],
    ];

    private const MAX_FILE_SIZE_KB = 20480; // 20 MB
    private const ALLOWED_MIMES = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/zip',
        'application/x-zip-compressed',
    ];

    public function __construct(protected ThesisRepositoryInterface $thesisRepository)
    {
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->thesisRepository->paginate($filters['per_page'] ?? 15, $filters);
    }

    public function find(int $id): Thesis
    {
        $thesis = $this->thesisRepository->find($id);

        if (!$thesis) {
            throw new NotFoundHttpException('Thesis not found.');
        }

        return $thesis;
    }

    public function create(array $data): Thesis
    {
        $data['status'] = Thesis::STATUS_DRAFT;

        return DB::transaction(function () use ($data) {
            $subject = ThesisSubject::query()
                ->lockForUpdate()
                ->whereKey($data['thesis_subject_id'] ?? null)
                ->where('student_id', $data['student_id'])
                ->where('status', ThesisSubject::STATUS_ACCEPTED)
                ->first();

            if (!$subject) {
                throw ValidationException::withMessages([
                    'thesis_subject_id' => ['An accepted subject assigned to this student is required.'],
                ]);
            }

            return $this->thesisRepository->create($data);
        });
    }

    public function update(Thesis $thesis, array $data): Thesis
    {
        return $this->thesisRepository->update($thesis, $data);
    }

    public function delete(Thesis $thesis): void
    {
        DB::transaction(function () use ($thesis) {
            foreach ($thesis->versions as $version) {
                Storage::disk('local')->delete($version->file_path);
            }

            $this->thesisRepository->delete($thesis);
        });
    }

    public function assignSupervisor(Thesis $thesis, int $supervisorId, User $actor): Thesis
    {
        Gate::forUser($actor)->authorize('assignSupervisor', $thesis);

        return DB::transaction(function () use ($thesis, $supervisorId, $actor) {
            $thesis = Thesis::query()->lockForUpdate()->findOrFail($thesis->id);
            $supervisor = Supervisor::query()
                ->whereKey($supervisorId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (!$supervisor || (int) $supervisor->department_id !== (int) $thesis->department_id) {
                throw ValidationException::withMessages([
                    'supervisor_id' => ['The selected supervisor must be active and belong to the thesis department.'],
                ]);
            }

            $assignedCount = Thesis::where('supervisor_id', $supervisor->id)
                ->whereNotIn('status', [Thesis::STATUS_ARCHIVED, Thesis::STATUS_REJECTED])
                ->where('id', '!=', $thesis->id)
                ->count();

            if ($assignedCount >= $supervisor->max_students) {
                throw ValidationException::withMessages([
                    'supervisor_id' => ['The selected supervisor has reached their thesis capacity.'],
                ]);
            }

            $previousSupervisorId = $thesis->supervisor_id;
            $updatedThesis = $this->thesisRepository->update($thesis, ['supervisor_id' => $supervisorId]);

            AuditLogger::log(
                action: 'thesis_supervisor_assigned',
                modelClass: Thesis::class,
                modelId: $thesis->id,
                oldValues: ['supervisor_id' => $previousSupervisorId],
                newValues: ['supervisor_id' => $supervisorId],
                user: $actor
            );

            return $updatedThesis;
        });
    }

    public function submit(Thesis $thesis, User $actor): Thesis
    {
        Gate::forUser($actor)->authorize('update', $thesis);

        [$thesis, $fromStatus, $toStatus] = DB::transaction(function () use ($thesis, $actor) {
            $thesis = Thesis::query()->lockForUpdate()->findOrFail($thesis->id);
            Gate::forUser($actor)->authorize('update', $thesis);
            $fromStatus = $thesis->status;
            $toStatus = Thesis::STATUS_SUBMITTED;

            $this->assertTransitionAllowed($fromStatus, $toStatus);

            $hasAcceptedSubject = ThesisSubject::query()
                ->whereKey($thesis->thesis_subject_id)
                ->where('student_id', $thesis->student_id)
                ->where('status', ThesisSubject::STATUS_ACCEPTED)
                ->exists();

            if (!$hasAcceptedSubject) {
                throw ValidationException::withMessages([
                    'thesis_subject_id' => ['An accepted subject assigned to this student is required before submission.'],
                ]);
            }

            if (!$thesis->versions()->exists()) {
                throw ValidationException::withMessages([
                    'file' => ['A thesis document must be uploaded before submission.'],
                ]);
            }

            $thesis->update(['status' => $toStatus, 'submitted_at' => now()]);

            ThesisStatusHistory::create([
                'thesis_id' => $thesis->id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'changed_by' => $actor->id,
                'remarks' => 'Thesis submitted for supervisor review.',
            ]);

            $submittedThesis = $thesis->fresh(['student.user', 'supervisor.user']);

            AuditLogger::log(
                action: 'thesis_status_changed',
                modelClass: Thesis::class,
                modelId: $thesis->id,
                oldValues: ['status' => $fromStatus],
                newValues: ['status' => $toStatus, 'remarks' => 'Thesis submitted for supervisor review.'],
                user: $actor
            );

            return [$submittedThesis, $fromStatus, $toStatus];
        });

        DB::afterCommit(fn () => event(new ThesisSubmitted($thesis)));

        return $thesis;
    }

    public function changeStatus(Thesis $thesis, string $toStatus, User $actor, ?string $remarks = null): Thesis
    {
        Gate::forUser($actor)->authorize('changeStatus', $thesis);

        return $this->transitionStatus($thesis, $toStatus, $actor, $remarks);
    }

    public function archiveStatus(Thesis $thesis, User $actor, ?string $remarks = null): Thesis
    {
        Gate::forUser($actor)->authorize('archive', [\App\Models\ThesisArchive::class, $thesis]);

        return $this->transitionStatus($thesis, Thesis::STATUS_ARCHIVED, $actor, $remarks, true);
    }

    private function transitionStatus(
        Thesis $thesis,
        string $toStatus,
        User $actor,
        ?string $remarks,
        bool $archiving = false
    ): Thesis {
        [$thesis, $fromStatus] = DB::transaction(function () use ($thesis, $toStatus, $actor, $remarks, $archiving) {
            $thesis = Thesis::query()->lockForUpdate()->findOrFail($thesis->id);
            Gate::forUser($actor)->authorize(
                $archiving ? 'archive' : 'changeStatus',
                $archiving ? [\App\Models\ThesisArchive::class, $thesis] : $thesis
            );
            $fromStatus = $thesis->status;

            if ($archiving && $fromStatus === Thesis::STATUS_APPROVED && $toStatus === Thesis::STATUS_ARCHIVED) {
                // Archival is a separate privileged workflow with additional eligibility checks.
            } else {
                $this->assertTransitionAllowed($fromStatus, $toStatus);
            }

            if ($toStatus === Thesis::STATUS_SUBMITTED) {
                $hasAcceptedSubject = ThesisSubject::query()
                    ->whereKey($thesis->thesis_subject_id)
                    ->where('student_id', $thesis->student_id)
                    ->where('status', ThesisSubject::STATUS_ACCEPTED)
                    ->exists();

                if (!$hasAcceptedSubject) {
                    throw ValidationException::withMessages([
                        'thesis_subject_id' => ['An accepted subject assigned to this student is required before submission.'],
                    ]);
                }
            }

            if ($actor->role?->slug === 'supervisor'
                && (!in_array($fromStatus, [Thesis::STATUS_SUBMITTED, Thesis::STATUS_UNDER_REVIEW], true)
                    || !in_array($toStatus, [
                        Thesis::STATUS_UNDER_REVIEW,
                        Thesis::STATUS_REVISION_REQUIRED,
                        Thesis::STATUS_APPROVED,
                        Thesis::STATUS_REJECTED,
                    ], true))) {
                throw new \Illuminate\Auth\Access\AuthorizationException();
            }

            $updateData = ['status' => $toStatus];

            if ($toStatus === Thesis::STATUS_APPROVED) {
                $updateData['approved_at'] = now();
            }

            $thesis->update($updateData);

            ThesisStatusHistory::create([
                'thesis_id' => $thesis->id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'changed_by' => $actor->id,
                'remarks' => $remarks,
            ]);

            AuditLogger::log(
                action: 'thesis_status_changed',
                modelClass: Thesis::class,
                modelId: $thesis->id,
                oldValues: ['status' => $fromStatus],
                newValues: ['status' => $toStatus, 'remarks' => $remarks],
                user: $actor
            );

            return [$thesis->fresh(['student.user', 'supervisor.user']), $fromStatus];
        });

        DB::afterCommit(fn () => event(new ThesisStatusChanged($thesis, $fromStatus, $toStatus, $remarks)));

        return $thesis;
    }

    public function uploadVersion(Thesis $thesis, UploadedFile $file, User $uploader, ?string $notes = null): ThesisVersion
    {
        Gate::forUser($uploader)->authorize('uploadVersion', $thesis);

        if (!in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
            throw ValidationException::withMessages(['file' => ['Only PDF, DOCX, and ZIP files are allowed.']]);
        }

        if ($file->getSize() > self::MAX_FILE_SIZE_KB * 1024) {
            throw ValidationException::withMessages(['file' => ['File size must not exceed 20MB.']]);
        }

        $path = null;

        try {
            return DB::transaction(function () use ($thesis, $file, $uploader, $notes, &$path) {
                $lockedThesis = Thesis::query()->lockForUpdate()->findOrFail($thesis->id);
                Gate::forUser($uploader)->authorize('uploadVersion', $lockedThesis);

                if (!in_array($lockedThesis->status, [Thesis::STATUS_DRAFT, Thesis::STATUS_REVISION_REQUIRED], true)) {
                    throw ValidationException::withMessages([
                        'file' => ['A new version can only be uploaded for a draft or a thesis requiring revision.'],
                    ]);
                }

                $nextVersion = ($lockedThesis->versions()->max('version_number') ?? 0) + 1;
                $path = $file->store("theses/{$lockedThesis->id}/versions", 'local');

                if (!$path) {
                    throw new \RuntimeException('The thesis document could not be stored.');
                }

                return ThesisVersion::create([
                    'thesis_id' => $lockedThesis->id,
                    'version_number' => $nextVersion,
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'uploaded_by' => $uploader->id,
                    'notes' => $notes,
                ]);
            });
        } catch (Throwable $exception) {
            if ($path && !Storage::disk('local')->delete($path)) {
                Log::error('Failed to remove an uncommitted thesis version file.', [
                    'thesis_id' => $thesis->id,
                    'file_path' => $path,
                ]);
            }

            throw $exception;
        }
    }

    public function addComment(Thesis $thesis, User $author, string $content, ?int $versionId = null): Comment
    {
        Gate::forUser($author)->authorize('comment', $thesis);

        if ($versionId !== null && !$thesis->versions()->whereKey($versionId)->exists()) {
            throw ValidationException::withMessages([
                'thesis_version_id' => ['The selected version does not belong to this thesis.'],
            ]);
        }

        $comment = Comment::create([
            'thesis_id' => $thesis->id,
            'thesis_version_id' => $versionId,
            'user_id' => $author->id,
            'content' => $content,
        ]);

        $comment->load('author', 'thesis.student.user', 'thesis.supervisor.user');

        event(new ThesisCommented($comment));

        return $comment;
    }

    private function assertTransitionAllowed(string $from, string $to): void
    {
        $allowed = self::TRANSITIONS[$from] ?? [];

        if (!in_array($to, $allowed, true)) {
            throw new InvalidStatusTransitionException($from, $to);
        }
    }
}