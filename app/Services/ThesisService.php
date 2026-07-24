<?php

namespace App\Services;

use App\Events\ThesisCommented;
use App\Events\ThesisStatusChanged;
use App\Events\ThesisSubmitted;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Comment;
use App\Models\Thesis;
use App\Models\ThesisStatusHistory;
use App\Models\ThesisVersion;
use App\Models\User;
use App\Repositories\Interfaces\ThesisRepositoryInterface;
use App\Services\Interfaces\ThesisServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        Thesis::STATUS_APPROVED => [Thesis::STATUS_ARCHIVED],
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

        return $this->thesisRepository->create($data);
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

    public function assignSupervisor(Thesis $thesis, int $supervisorId): Thesis
    {
        return $this->thesisRepository->update($thesis, ['supervisor_id' => $supervisorId]);
    }

    public function submit(Thesis $thesis): Thesis
    {
        if ($thesis->versions()->count() === 0) {
            throw new \RuntimeException('A thesis document must be uploaded before submission.');
        }

        $fromStatus = $thesis->status;
        $toStatus = $fromStatus === Thesis::STATUS_REVISION_REQUIRED
            ? Thesis::STATUS_SUBMITTED
            : Thesis::STATUS_SUBMITTED;

        $this->assertTransitionAllowed($fromStatus, $toStatus);

        $thesis = DB::transaction(function () use ($thesis, $fromStatus, $toStatus) {
            $thesis->update(['status' => $toStatus, 'submitted_at' => now()]);

            ThesisStatusHistory::create([
                'thesis_id' => $thesis->id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'changed_by' => $thesis->student->user_id,
                'remarks' => 'Thesis submitted for supervisor review.',
            ]);

            return $thesis->fresh(['student.user', 'supervisor.user']);
        });

        event(new ThesisSubmitted($thesis));

        return $thesis;
    }

    public function changeStatus(Thesis $thesis, string $toStatus, User $actor, ?string $remarks = null): Thesis
    {
        $fromStatus = $thesis->status;
        $this->assertTransitionAllowed($fromStatus, $toStatus);

        $thesis = DB::transaction(function () use ($thesis, $fromStatus, $toStatus, $actor, $remarks) {
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

            return $thesis->fresh(['student.user', 'supervisor.user']);
        });

        event(new ThesisStatusChanged($thesis, $fromStatus, $toStatus, $remarks));

        return $thesis;
    }

    public function uploadVersion(Thesis $thesis, UploadedFile $file, User $uploader, ?string $notes = null): ThesisVersion
    {
        if (!in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
            throw new \InvalidArgumentException('Only PDF, DOCX, and ZIP files are allowed.');
        }

        if ($file->getSize() > self::MAX_FILE_SIZE_KB * 1024) {
            throw new \InvalidArgumentException('File size must not exceed 20MB.');
        }

        return DB::transaction(function () use ($thesis, $file, $uploader, $notes) {
            $nextVersion = ($thesis->versions()->max('version_number') ?? 0) + 1;

            $path = $file->store("theses/{$thesis->id}/versions", 'local');

            $version = ThesisVersion::create([
                'thesis_id' => $thesis->id,
                'version_number' => $nextVersion,
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'uploaded_by' => $uploader->id,
                'notes' => $notes,
            ]);

            if ($thesis->status === Thesis::STATUS_DRAFT && $nextVersion === 1) {
                // first upload keeps thesis in draft until explicit submission
            }

            return $version;
        });
    }

    public function addComment(Thesis $thesis, User $author, string $content, ?int $versionId = null): Comment
    {
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