<?php

namespace App\Services;

use App\Exceptions\SchedulingConflictException;
use App\Models\DefenseRoom;
use App\Models\DefenseResult;
use App\Models\DefenseSchedule;
use App\Models\JuryMember;
use App\Models\Thesis;
use App\Models\User;
use App\Notifications\DefenseScheduledNotification;
use App\Notifications\DefenseUpdatedNotification;
use App\Repositories\Interfaces\DefenseScheduleRepositoryInterface;
use App\Services\Interfaces\DefenseScheduleServiceInterface;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
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
        Gate::forUser($actor)->authorize('create', DefenseSchedule::class);

        $start = Carbon::parse($data['scheduled_at']);
        $duration = $data['duration_minutes'] ?? 60;
        $end = $start->copy()->addMinutes($duration);

        return DB::transaction(function () use ($data, $start, $end, $duration, $actor) {
            $thesis = Thesis::query()->with('department')->lockForUpdate()->findOrFail($data['thesis_id']);

            if ($actor->role?->slug === 'head-of-department'
                && (int) $thesis->department?->head_of_department_id !== (int) $actor->id) {
                throw new AuthorizationException();
            }

            if ($this->repository->findByThesisId($thesis->id)) {
                throw ValidationException::withMessages([
                    'thesis_id' => ['A defense is already scheduled for this thesis.'],
                ]);
            }

            $room = DefenseRoom::query()
                ->whereKey($data['defense_room_id'])
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (!$room) {
                throw ValidationException::withMessages([
                    'defense_room_id' => ['The selected room is not active.'],
                ]);
            }

            if ($this->repository->hasRoomConflict($room->id, $start, $end)) {
                throw new SchedulingConflictException('The selected room is already booked for this time slot.');
            }

            $schedule = $this->repository->create([
                'thesis_id' => $data['thesis_id'],
                'defense_room_id' => $data['defense_room_id'],
                'scheduled_at' => $start,
                'duration_minutes' => $duration,
                'status' => DefenseSchedule::STATUS_SCHEDULED,
                'created_by' => $actor->id,
            ]);

            AuditLogger::log(
                action: 'defense_scheduled',
                modelClass: DefenseSchedule::class,
                modelId: $schedule->id,
                newValues: $schedule->only(['thesis_id', 'defense_room_id', 'scheduled_at', 'duration_minutes', 'status']),
                user: $actor
            );

            return $schedule->load(['thesis.student.user', 'room']);
        });
    }

    public function reschedule(DefenseSchedule $schedule, array $data, User $actor): DefenseSchedule
    {
        Gate::forUser($actor)->authorize('update', $schedule);

        $start = Carbon::parse($data['scheduled_at'] ?? $schedule->scheduled_at);
        $duration = $data['duration_minutes'] ?? $schedule->duration_minutes;
        $end = $start->copy()->addMinutes($duration);
        $roomId = $data['defense_room_id'] ?? $schedule->defense_room_id;

        $schedule = DB::transaction(function () use ($schedule, $roomId, $start, $end, $duration, $actor) {
            $schedule = DefenseSchedule::query()->lockForUpdate()->findOrFail($schedule->id);
            Gate::forUser($actor)->authorize('update', $schedule);

            if ($schedule->status !== DefenseSchedule::STATUS_SCHEDULED) {
                throw ValidationException::withMessages([
                    'status' => ['Only a scheduled defense can be rescheduled.'],
                ]);
            }

            $lockedRoom = DefenseRoom::query()
                ->whereKey($roomId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (!$lockedRoom) {
                throw ValidationException::withMessages([
                    'defense_room_id' => ['The selected room is not active.'],
                ]);
            }

            if ($this->repository->hasRoomConflict($roomId, $start, $end, $schedule->id)) {
                throw new SchedulingConflictException('The selected room is already booked for this time slot.');
            }

            $juryMemberIds = $schedule->juryMembers()->pluck('jury_members.id')->all();
            if (!empty($juryMemberIds) && $this->repository->hasJuryConflict($juryMemberIds, $start, $end, $schedule->id)) {
                throw new SchedulingConflictException('One or more assigned jury members are unavailable at this time.');
            }

            $oldValues = $schedule->only(['defense_room_id', 'scheduled_at', 'duration_minutes', 'status']);
            $updatedSchedule = $this->repository->update($schedule, [
                'defense_room_id' => $roomId,
                'scheduled_at' => $start,
                'duration_minutes' => $duration,
                'status' => DefenseSchedule::STATUS_SCHEDULED,
            ]);

            AuditLogger::log(
                action: 'defense_rescheduled',
                modelClass: DefenseSchedule::class,
                modelId: $schedule->id,
                oldValues: $oldValues,
                newValues: $updatedSchedule->only(['defense_room_id', 'scheduled_at', 'duration_minutes', 'status']),
                user: $actor
            );

            return $updatedSchedule;
        });

        DB::afterCommit(fn () => $this->notifyParticipants($schedule, new DefenseUpdatedNotification($schedule, 'Schedule updated')));
        return $schedule;
    }

    public function assignJury(DefenseSchedule $schedule, array $juryAssignments, User $actor): DefenseSchedule
    {
        Gate::forUser($actor)->authorize('assignJury', $schedule);

        $roles = array_column($juryAssignments, 'role');
        if (array_diff(self::REQUIRED_JURY_ROLES, $roles) !== [] || count($roles) !== 3) {
            throw ValidationException::withMessages([
                'jury' => ['Exactly one president, one examiner, and one reporter must be assigned.'],
            ]);
        }

        $juryMemberIds = array_column($juryAssignments, 'jury_member_id');
        if (count($juryMemberIds) !== count(array_unique($juryMemberIds))) {
            throw ValidationException::withMessages([
                'jury' => ['A jury member cannot hold two roles on the same panel.'],
            ]);
        }

        $schedule = DB::transaction(function () use ($schedule, $juryAssignments, $juryMemberIds, $actor) {
            $schedule = DefenseSchedule::query()->lockForUpdate()->findOrFail($schedule->id);
            Gate::forUser($actor)->authorize('assignJury', $schedule);

            if ($schedule->status !== DefenseSchedule::STATUS_SCHEDULED) {
                throw ValidationException::withMessages([
                    'status' => ['Jury members can only be assigned to a scheduled defense.'],
                ]);
            }

            $juryMembers = JuryMember::query()
                ->whereIn('id', $juryMemberIds)
                ->where('is_active', true)
                ->lockForUpdate()
                ->get();

            if ($juryMembers->count() !== count($juryMemberIds)) {
                throw ValidationException::withMessages([
                    'jury' => ['Every assigned jury member must be active.'],
                ]);
            }

            if ($this->repository->hasJuryConflict($juryMemberIds, $schedule->scheduled_at, $schedule->ends_at, $schedule->id)) {
                throw new SchedulingConflictException('One or more selected jury members are already booked for this time slot.');
            }

            $supervisorUserId = $schedule->thesis->supervisor?->user_id;
            $juryUserIds = $juryMembers->pluck('user_id')->map(fn ($id) => (int) $id)->all();

            if ($supervisorUserId && in_array((int) $supervisorUserId, $juryUserIds, true)) {
                throw ValidationException::withMessages([
                    'jury' => ['The thesis supervisor cannot also serve as a jury member for the same defense.'],
                ]);
            }

            $oldJury = $schedule->juryMembers()->get()->map(fn (JuryMember $member) => [
                'jury_member_id' => $member->id,
                'role' => $member->pivot->role,
            ])->all();
            $syncData = [];
            foreach ($juryAssignments as $assignment) {
                $syncData[$assignment['jury_member_id']] = ['role' => $assignment['role']];
            }

            $schedule->juryMembers()->sync($syncData);

            $updatedSchedule = $schedule->fresh(['thesis.student.user', 'room', 'juryMembers.user']);

            AuditLogger::log(
                action: 'defense_jury_assigned',
                modelClass: DefenseSchedule::class,
                modelId: $schedule->id,
                oldValues: ['jury' => $oldJury],
                newValues: ['jury' => $juryAssignments],
                user: $actor
            );

            return $updatedSchedule;
        });

        DB::afterCommit(function () use ($schedule) {
            $notification = new DefenseScheduledNotification($schedule);
            $this->notifyParticipants($schedule, $notification);

            foreach ($schedule->juryMembers as $jury) {
                $jury->user->notify($notification);
            }
        });

        return $schedule;
    }

    public function setPublic(DefenseSchedule $schedule, bool $isPublic, User $actor): DefenseSchedule
    {
        Gate::forUser($actor)->authorize('publish', $schedule);

        return DB::transaction(function () use ($schedule, $isPublic, $actor) {
            $schedule = DefenseSchedule::query()->lockForUpdate()->findOrFail($schedule->id);
            Gate::forUser($actor)->authorize('publish', $schedule);

            if ($schedule->status !== DefenseSchedule::STATUS_SCHEDULED && $isPublic) {
                throw ValidationException::withMessages([
                    'is_public' => ['Only a scheduled defense can be made public.'],
                ]);
            }

            $oldValue = $schedule->is_public;
            $schedule->update(['is_public' => $isPublic]);

            AuditLogger::log(
                action: 'defense_publication_changed',
                modelClass: DefenseSchedule::class,
                modelId: $schedule->id,
                oldValues: ['is_public' => $oldValue],
                newValues: ['is_public' => $isPublic],
                user: $actor
            );

            return $schedule->fresh(['thesis.student.user', 'thesis.department', 'thesis.program', 'room', 'juryMembers.user']);
        });
    }

    public function cancel(DefenseSchedule $schedule, string $reason, User $actor): DefenseSchedule
    {
        Gate::forUser($actor)->authorize('update', $schedule);

        $schedule = DB::transaction(function () use ($schedule, $reason, $actor) {
            $schedule = DefenseSchedule::query()->lockForUpdate()->findOrFail($schedule->id);
            Gate::forUser($actor)->authorize('update', $schedule);

            if ($schedule->status !== DefenseSchedule::STATUS_SCHEDULED) {
                throw ValidationException::withMessages([
                    'status' => ['Only a scheduled defense can be cancelled.'],
                ]);
            }

            $updatedSchedule = $this->repository->update($schedule, ['status' => DefenseSchedule::STATUS_CANCELLED]);
            AuditLogger::log(
                action: 'defense_cancelled',
                modelClass: DefenseSchedule::class,
                modelId: $schedule->id,
                oldValues: ['status' => DefenseSchedule::STATUS_SCHEDULED],
                newValues: ['status' => DefenseSchedule::STATUS_CANCELLED, 'reason' => $reason],
                user: $actor
            );

            return $updatedSchedule;
        });

        DB::afterCommit(fn () => $this->notifyParticipants(
            $schedule,
            new DefenseUpdatedNotification($schedule, "Cancelled: {$reason}")
        ));
        return $schedule;
    }

    public function recordResult(DefenseSchedule $schedule, array $data, User $actor): DefenseResult
    {
        Gate::forUser($actor)->authorize('recordResult', $schedule);

        return DB::transaction(function () use ($schedule, $data, $actor) {
            $schedule = DefenseSchedule::query()->lockForUpdate()->findOrFail($schedule->id);
            Gate::forUser($actor)->authorize('recordResult', $schedule);

            if ($schedule->status !== DefenseSchedule::STATUS_SCHEDULED) {
                throw ValidationException::withMessages([
                    'status' => ['A final result can only be recorded once for a scheduled defense.'],
                ]);
            }

            $existingResult = DefenseResult::where('defense_schedule_id', $schedule->id)->first();
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

            AuditLogger::log(
                action: 'defense_result_recorded',
                modelClass: DefenseResult::class,
                modelId: $result->id,
                oldValues: $existingResult?->only(['final_grade', 'verdict', 'remarks', 'recorded_by']),
                newValues: $result->only(['defense_schedule_id', 'final_grade', 'verdict', 'remarks', 'recorded_by']),
                user: $actor
            );

            return $result;
        });
    }

    private function notifyParticipants(DefenseSchedule $schedule, $notification): void
    {
        $schedule->thesis->student->user->notify($notification);
        $schedule->thesis->supervisor?->user?->notify($notification);
    }
}