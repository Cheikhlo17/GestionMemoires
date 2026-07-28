<?php

namespace App\Repositories\Interfaces;

use App\Models\DefenseSchedule;
use Illuminate\Support\Collection;

interface DefenseScheduleRepositoryInterface
{
    public function calendar(\DateTimeInterface $from, \DateTimeInterface $to, array $filters): Collection;

    public function find(int $id): ?DefenseSchedule;

    public function findByThesisId(int $thesisId): ?DefenseSchedule;

    public function create(array $data): DefenseSchedule;

    public function update(DefenseSchedule $schedule, array $data): DefenseSchedule;

    public function delete(DefenseSchedule $schedule): bool;

    public function hasRoomConflict(int $roomId, \DateTimeInterface $start, \DateTimeInterface $end, ?int $exceptId = null): bool;

    public function hasJuryConflict(array $juryMemberIds, \DateTimeInterface $start, \DateTimeInterface $end, ?int $exceptId = null): bool;
}