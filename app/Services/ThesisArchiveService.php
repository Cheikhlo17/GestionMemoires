<?php

namespace App\Services;

use App\Exceptions\ThesisNotEligibleForArchivingException;
use App\Models\DefenseResult;
use App\Models\DefenseSchedule;
use App\Models\Thesis;
use App\Models\ThesisArchive;
use App\Models\User;
use App\Services\Interfaces\ThesisArchiveServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ThesisArchiveService implements ThesisArchiveServiceInterface
{
    public function checkEligibility(Thesis $thesis): array
    {
        $reasons = [];

        if ($thesis->archive()->exists()) {
            $reasons[] = 'This thesis has already been archived.';
        }

        if ($thesis->status !== Thesis::STATUS_APPROVED) {
            $reasons[] = 'The thesis must be in "approved" status before archiving.';
        }

        $finalPdfVersion = $thesis->versions()->where('mime_type', 'application/pdf')->first();
        if (!$finalPdfVersion) {
            $reasons[] = 'The thesis must have at least one PDF version uploaded.';
        }

        $defenseSchedule = DefenseSchedule::where('thesis_id', $thesis->id)
            ->where('status', DefenseSchedule::STATUS_COMPLETED)
            ->first();
        if (!$defenseSchedule) {
            $reasons[] = 'The defense must be completed before archiving.';
        }

        if ($defenseSchedule) {
            $result = DefenseResult::where('defense_schedule_id', $defenseSchedule->id)->first();
            if (!$result || !$result->verdict) {
                $reasons[] = 'A final defense result must be recorded before archiving.';
            } elseif ($result->verdict === 'fail') {
                $reasons[] = 'A failed defense cannot be archived.';
            }
        }

        return $reasons;
    }

    public function archive(Thesis $thesis, array $data, User $actor): ThesisArchive
    {
        $reasons = $this->checkEligibility($thesis);

        if (!empty($reasons)) {
            throw new ThesisNotEligibleForArchivingException($reasons);
        }

        return DB::transaction(function () use ($thesis, $data, $actor) {
            $finalVersion = $thesis->versions()->where('mime_type', 'application/pdf')->first();
            $defenseSchedule = DefenseSchedule::where('thesis_id', $thesis->id)
                ->where('status', DefenseSchedule::STATUS_COMPLETED)
                ->first();

            $archive = ThesisArchive::create([
                'thesis_id' => $thesis->id,
                'thesis_version_id' => $data['thesis_version_id'] ?? $finalVersion->id,
                'defense_schedule_id' => $defenseSchedule?->id,
                'keywords' => $data['keywords'] ?? [],
                'visibility' => $data['visibility'] ?? ThesisArchive::VISIBILITY_PRIVATE,
                'show_final_grade' => $data['show_final_grade'] ?? false,
                'archived_by' => $actor->id,
                'archived_at' => now(),
            ]);

            $thesis->update(['status' => Thesis::STATUS_ARCHIVED]);

            AuditLogger::log(
                action: 'thesis_archived',
                modelClass: ThesisArchive::class,
                modelId: $archive->id,
                newValues: $archive->only(['thesis_id', 'visibility', 'show_final_grade']),
                user: $actor
            );

            return $archive->load(['thesis.student.user', 'thesis.supervisor.user', 'finalVersion', 'defenseSchedule.juryMembers.user']);
        });
    }

    public function update(ThesisArchive $archive, array $data, User $actor): ThesisArchive
    {
        $oldValues = $archive->only(['visibility', 'show_final_grade', 'keywords']);

        $archive->update(array_filter([
            'keywords' => $data['keywords'] ?? null,
            'visibility' => $data['visibility'] ?? null,
            'show_final_grade' => $data['show_final_grade'] ?? null,
        ], fn ($v) => $v !== null));

        AuditLogger::log(
            action: 'thesis_archive_updated',
            modelClass: ThesisArchive::class,
            modelId: $archive->id,
            oldValues: $oldValues,
            newValues: $archive->only(['visibility', 'show_final_grade', 'keywords']),
            user: $actor
        );

        return $archive->fresh(['thesis.student.user', 'thesis.supervisor.user', 'finalVersion', 'defenseSchedule.juryMembers.user']);
    }

    public function delete(ThesisArchive $archive, User $actor): void
    {
        DB::transaction(function () use ($archive, $actor) {
            AuditLogger::log(
                action: 'thesis_archive_deleted',
                modelClass: ThesisArchive::class,
                modelId: $archive->id,
                oldValues: $archive->only(['thesis_id', 'visibility']),
                user: $actor
            );

            $archive->delete();
        });
    }

    public function listPublic(array $filters): LengthAwarePaginator
    {
        $filters['visibility'] = ThesisArchive::VISIBILITY_PUBLIC;

        return $this->query($filters)->paginate($filters['per_page'] ?? 20);
    }

    public function listAdmin(array $filters): LengthAwarePaginator
    {
        return $this->query($filters)->paginate($filters['per_page'] ?? 20);
    }

    private function query(array $filters)
    {
        $query = ThesisArchive::with([
            'thesis.student.user',
            'thesis.supervisor.user',
            'thesis.department',
            'thesis.program',
            'thesis.academicYear',
            'finalVersion',
            'defenseSchedule.juryMembers.user',
        ]);

        if (!empty($filters['visibility'])) {
            $query->where('visibility', $filters['visibility']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('thesis', function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhereHas('student.user', function ($uq) use ($search) {
                        $uq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('supervisor.user', function ($uq) use ($search) {
                        $uq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            })->orWhereJsonContains('keywords', $search);
        }

        if (!empty($filters['academic_year_id'])) {
            $query->whereHas('thesis', fn ($q) => $q->where('academic_year_id', $filters['academic_year_id']));
        }

        if (!empty($filters['program_id'])) {
            $query->whereHas('thesis', fn ($q) => $q->where('program_id', $filters['program_id']));
        }

        if (!empty($filters['department_id'])) {
            $query->whereHas('thesis', fn ($q) => $q->where('department_id', $filters['department_id']));
        }

        if (!empty($filters['degree_level'])) {
            $query->whereHas('thesis.program', fn ($q) => $q->where('degree_level', $filters['degree_level']));
        }

        if (!empty($filters['supervisor_id'])) {
            $query->whereHas('thesis', fn ($q) => $q->where('supervisor_id', $filters['supervisor_id']));
        }

        if (!empty($filters['defense_year'])) {
            $query->whereHas('defenseSchedule', fn ($q) => $q->whereYear('scheduled_at', $filters['defense_year']));
        }

        return $query->orderByDesc('archived_at');
    }
}