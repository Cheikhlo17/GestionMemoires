<?php

namespace App\Services\Interfaces;

use App\Models\DefenseResult;
use App\Models\DefenseSchedule;
use App\Models\User;
use Illuminate\Support\Collection;

interface DefenseScheduleServiceInterface
{
    public function calendar(\DateTimeInterface $from, \DateTimeInterface $to, array $filters): Collection;

    public function find(int $id): DefenseSchedule;

    public function schedule(array $data, User $actor): DefenseSchedule;

    public function reschedule(DefenseSchedule $schedule, array $data, User $actor): DefenseSchedule;

    public function assignJury(DefenseSchedule $schedule, array $juryAssignments): DefenseSchedule;

    public function cancel(DefenseSchedule $schedule, string $reason, User $actor): DefenseSchedule;

    public function recordResult(DefenseSchedule $schedule, array $data, User $actor): DefenseResult;
}