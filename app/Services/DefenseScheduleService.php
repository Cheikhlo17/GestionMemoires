<?php

namespace App\Services;

use App\Exceptions\SchedulingConflictException;
use App\Models\DefenseResult;
use App\Models\DefenseSchedule;
use App\Models\JuryMember;
use App\Models\User;
use App\Notifications\DefenseScheduledNotification;
use App\Notifications\DefenseUpdatedNotification;
use App\Repositories\Interfaces\DefenseScheduleRepositoryInterface;
use App\Services\Interfaces\DefenseScheduleServiceInterface;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DefenseScheduleService implements DefenseScheduleServiceInterface
{
    private const REQUIRED_JURY_ROLES = ['president', 'examiner', 'reporter'];

    public function __construct(protected DefenseScheduleRepositoryInterface $repository)
    {
    }

    public function calendar(\DateTimeInterface $from, \DateTimeInterface $to, array $filters): Collection
    {
        return $this->repository->calendar($from, $to, $filters);
    }

    public function find(int $id): DefenseSchedule
    {
        $schedule = $this->repository->find($id);

        if (!$schedule) {
            throw new NotFoundHttpException('Defense schedule not found.');
        }

        return $schedule;
    }

    public function schedule(array $data, User $actor): DefenseSchedule
    {
        $start = Carbon::parse($data['scheduled_at']);
        $duration = $data['duration_minutes'] ?? 60;
        $end = $start->copy()->addMinutes($duration);

        if ($this->repository->hasRoomConflict($data['defense_room_id'], $start, $end)) {
            throw new SchedulingConflictException('The selected room is already booked for this time slot.');
        }

        $schedule = DB::transaction(function () use ($data, $start, $duration, $actor) {
            return $this->repository->create([
                'thesis_id' => $data['thesis_id'],
                'defense_room_id' => $data['defense_room_id'],
                'scheduled_at' => $start,
                'duration_minutes' => $duration,
                'status' => DefenseSchedule::STATUS_SCHEDULED,
                'created_by' => $actor->id,
            ]);
        });

        return $schedule->load(['thesis.student.user', 'room']);
    }

    public function reschedule(DefenseSchedule $schedule, array $data, User $actor): DefenseSchedule
    {
        $start = Carbon::parse($data['scheduled_at'] ?? $schedule->scheduled_at);
        $duration = $data['duration_minutes'] ?? $schedule->duration_minutes;
        $end = $start->copy()->addMinutes($duration);
        $roomId = $data['defense_room_id'] ?? $schedule->defense_room_id;

        if ($this->repository->hasRoomConflict($roomId, $start, $end, $schedule->id)) {
            throw new SchedulingConflictException('The selected room is already booked for this time slot.');
        }

        $juryMemberIds = $schedule->juryMembers()->pluck('jury_members.id')->all();
        if (!empty($juryMemberIds) && $this->repository->hasJuryConflict($juryMemberIds, $start, $end, $schedule->id)) {
            throw new SchedulingConflictException('One or more assigned jury members are unavailable at this time.');
        }

        $schedule = $this->repository->update($schedule, [
            'defense_room_id' => $roomId,
            'scheduled_at' => $start,
            'duration_minutes' => $duration,
            'status' => DefenseSchedule::STATUS_SCHEDULED,
        ]);

        $this->notifyParticipants($schedule, new DefenseUpdatedNotification($schedule, 'Schedule updated'));

        return $schedule;
    }

    public function assignJury(DefenseSchedule $schedule, array $juryAssignments): DefenseSchedule
    {
        $roles = array_column($juryAssignments, 'role');
        if (array_diff(self::REQUIRED_JURY_ROLES, $roles) !== [] || count($roles) !== 3) {
            throw new \InvalidArgumentException('Exactly one president, one examiner, and one reporter must be assigned.');
        }

        $juryMemberIds = array_column($juryAssignments, 'jury_member_id');
        if (count($juryMemberIds) !== count(array_unique($juryMemberIds))) {
            throw new \InvalidArgumentException('A jury member cannot hold two roles on the same panel.');
        }

        $start = $schedule->scheduled_at;
        $end = $schedule->ends_at;

        if ($this->repository->hasJuryConflict($juryMemberIds, $start, $end, $schedule->id)) {
            throw new SchedulingConflictException('One or more selected jury members are already booked for this time slot.');
        }

        $supervisorUserId = $schedule->thesis->supervisor?->user_id;
        $juryUserIds = JuryMember::whereIn('id', $juryMemberIds)->pluck('user_id', 'id');

        if ($supervisorUserId && in_array($supervisorUserId, $juryUserIds->all(), true)) {
            throw new \InvalidArgumentException('The thesis supervisor cannot also serve as a jury member for the same defense.');
        }

        $schedule = DB::transaction(function () use ($schedule, $juryAssignments) {
            $syncData = [];
            foreach ($juryAssignments as $assignment) {
                $syncData[$assignment['jury_member_id']] = ['role' => $assignment['role']];
            }

            $schedule->juryMembers()->sync($syncData);

            return $schedule->fresh(['thesis.student.user', 'room', 'juryMembers.user']);
        });

        $this->notifyParticipants($schedule, new DefenseScheduledNotification($schedule));

        foreach ($schedule->juryMembers as $jury) {
            $jury->user->notify(new DefenseScheduledNotification($schedule));
        }

        return $schedule;
    }

    public function cancel(DefenseSchedule $schedule, string $reason, User $actor): DefenseSchedule
    {
        $schedule = $this->repository->update($schedule, ['status' => DefenseSchedule::STATUS_CANCELLED]);

        $this->notifyParticipants($schedule, new DefenseUpdatedNotification($schedule, "Cancelled: {$reason}"));

        return $schedule;
    }

    public function recordResult(DefenseSchedule $schedule, array $data, User $actor): DefenseResult
    {
        return DB::transaction(function () use ($schedule, $data, $actor) {
            $result = DefenseResult::updateOrCreate(
                ['defense_schedule_id' => $schedule->id],
                [
                    'final_grade' => $data['final_grade'] ?? null,
                    'verdict' => $data['verdict'],
                    'remarks' => $data['remarks'] ?? null,
                    'recorded_by' => $actor->id,
                    'recorded_at' => now(),
                ]
            );

            $schedule->update(['status' => DefenseSchedule::STATUS_COMPLETED]);

            return $result;
        });
    }

    private function notifyParticipants(DefenseSchedule $schedule, $notification): void
    {
        $schedule->thesis->student->user->notify($notification);
        $schedule->thesis->supervisor?->user?->notify($notification);
    }
}