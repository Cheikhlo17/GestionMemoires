<?php

namespace App\Repositories;

use App\Models\DefenseSchedule;
use App\Repositories\Interfaces\DefenseScheduleRepositoryInterface;
use Illuminate\Support\Collection;

class DefenseScheduleRepository implements DefenseScheduleRepositoryInterface
{
    public function __construct(protected DefenseSchedule $model)
    {
    }

    public function calendar(\DateTimeInterface $from, \DateTimeInterface $to, array $filters): Collection
    {
        $query = $this->model
            ->with(['thesis.student.user', 'room', 'juryMembers.user'])
            ->whereBetween('scheduled_at', [$from, $to]);

        if (!empty($filters['room_id'])) {
            $query->where('defense_room_id', $filters['room_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $this->applyViewerScope($query, $filters);

        return $query->orderBy('scheduled_at')->get();
    }

    public function find(int $id): ?DefenseSchedule
    {
        return $this->model
            ->with(['thesis.student.user', 'thesis.supervisor.user', 'room', 'juryMembers.user', 'result'])
            ->find($id);
    }

    public function findByThesisId(int $thesisId): ?DefenseSchedule
    {
        return $this->model->where('thesis_id', $thesisId)->first();
    }

    public function create(array $data): DefenseSchedule
    {
        return $this->model->create($data);
    }

    public function update(DefenseSchedule $schedule, array $data): DefenseSchedule
    {
        $schedule->update($data);

        return $schedule->fresh(['thesis.student.user', 'room', 'juryMembers.user']);
    }

    public function delete(DefenseSchedule $schedule): bool
    {
        return (bool) $schedule->delete();
    }

    public function hasRoomConflict(int $roomId, \DateTimeInterface $start, \DateTimeInterface $end, ?int $exceptId = null): bool
    {
        $candidateSchedules = $this->model
            ->where('defense_room_id', $roomId)
            ->where('status', '!=', DefenseSchedule::STATUS_CANCELLED)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->where('scheduled_at', '<', $end)
            ->get(['scheduled_at', 'duration_minutes']);

        return $candidateSchedules->contains(
            fn (DefenseSchedule $schedule) => $schedule->ends_at > $start
        );
    }

    public function hasJuryConflict(array $juryMemberIds, \DateTimeInterface $start, \DateTimeInterface $end, ?int $exceptId = null): bool
    {
        $candidateSchedules = $this->model
            ->whereHas('juryMembers', function ($q) use ($juryMemberIds) {
                $q->whereIn('jury_members.id', $juryMemberIds);
            })
            ->where('status', '!=', DefenseSchedule::STATUS_CANCELLED)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->where('scheduled_at', '<', $end)
            ->get(['scheduled_at', 'duration_minutes']);

        return $candidateSchedules->contains(
            fn (DefenseSchedule $schedule) => $schedule->ends_at > $start
        );
    }

    private function applyViewerScope($query, array $filters): void
    {
        $viewerRole = $filters['_viewer_role'] ?? null;
        $viewerId = $filters['_viewer_id'] ?? null;

        if ($viewerRole === 'administrator') {
            return;
        }

        if ($viewerRole === 'head-of-department') {
            $query->whereHas('thesis.department', fn ($q) => $q->where('head_of_department_id', $viewerId));
        } elseif ($viewerRole === 'student') {
            $query->whereHas('thesis.student', fn ($q) => $q->where('user_id', $viewerId));
        } elseif ($viewerRole === 'supervisor') {
            $query->whereHas('thesis.supervisor', fn ($q) => $q->where('user_id', $viewerId));
        } elseif ($viewerRole === 'jury-member') {
            $query->whereHas('juryMembers', fn ($q) => $q->where('jury_members.user_id', $viewerId));
        } else {
            $query->whereKey(0);
        }
    }
}