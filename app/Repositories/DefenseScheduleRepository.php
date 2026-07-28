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
        return $this->model
            ->where('defense_room_id', $roomId)
            ->where('status', '!=', DefenseSchedule::STATUS_CANCELLED)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->where(function ($q) use ($start, $end) {
                $q->whereRaw('scheduled_at < ? AND DATE_ADD(scheduled_at, INTERVAL duration_minutes MINUTE) > ?', [$end, $start]);
            })
            ->exists();
    }

    public function hasJuryConflict(array $juryMemberIds, \DateTimeInterface $start, \DateTimeInterface $end, ?int $exceptId = null): bool
    {
        return $this->model
            ->whereHas('juryMembers', function ($q) use ($juryMemberIds) {
                $q->whereIn('jury_members.id', $juryMemberIds);
            })
            ->where('status', '!=', DefenseSchedule::STATUS_CANCELLED)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->where(function ($q) use ($start, $end) {
                $q->whereRaw('scheduled_at < ? AND DATE_ADD(scheduled_at, INTERVAL duration_minutes MINUTE) > ?', [$end, $start]);
            })
            ->exists();
    }
}