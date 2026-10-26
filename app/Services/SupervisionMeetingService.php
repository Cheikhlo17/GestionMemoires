<?php

namespace App\Services;

use App\Models\SupervisionMeeting;
use App\Models\SupervisionMeetingAction;
use App\Models\SupervisionMeetingHistory;
use App\Models\Thesis;
use App\Models\User;
use App\Services\Interfaces\SupervisionMeetingServiceInterface;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SupervisionMeetingService implements SupervisionMeetingServiceInterface
{
    public function list(User $viewer, array $filters): LengthAwarePaginator
    {
        $query = SupervisionMeeting::query()
            ->with(['thesis', 'participants', 'actions.assignee', 'creator', 'history.changedBy'])
            ->whereHas('participants', fn ($participants) => $participants->whereKey($viewer->id));

        if (!empty($filters['thesis_id'])) {
            $query->where('thesis_id', $filters['thesis_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderByDesc('scheduled_at')->paginate($filters['per_page'] ?? 15);
    }

    public function create(Thesis $thesis, array $data, User $actor): SupervisionMeeting
    {
        Gate::forUser($actor)->authorize('createMeeting', $thesis);

        return DB::transaction(function () use ($thesis, $data, $actor) {
            $thesis = Thesis::query()->with(['student.user', 'supervisor.user'])->lockForUpdate()->findOrFail($thesis->id);
            Gate::forUser($actor)->authorize('createMeeting', $thesis);

            if (!$thesis->supervisor) {
                throw ValidationException::withMessages([
                    'thesis_id' => ['A supervisor must be assigned before scheduling a supervision meeting.'],
                ]);
            }

            $participants = [$thesis->student->user_id, $thesis->supervisor->user_id];
            $start = Carbon::parse($data['scheduled_at']);
            $duration = $data['duration_minutes'] ?? 60;
            $end = $start->copy()->addMinutes($duration);
            User::whereKey($participants)->orderBy('id')->lockForUpdate()->get();
            $this->assertNoParticipantConflict($participants, $start, $end);

            $meeting = SupervisionMeeting::create([
                ...$data,
                'thesis_id' => $thesis->id,
                'created_by' => $actor->id,
                'duration_minutes' => $duration,
                'status' => SupervisionMeeting::STATUS_SCHEDULED,
            ]);
            $meeting->participants()->sync($participants);
            $this->recordHistory($meeting, null, 'meeting_created', $actor, null, $meeting->only([
                'scheduled_at', 'duration_minutes', 'mode', 'location', 'agenda',
            ]));

            return $meeting->load(['thesis', 'participants', 'actions.assignee', 'creator', 'history.changedBy']);
        });
    }

    public function update(SupervisionMeeting $meeting, array $data, User $actor): SupervisionMeeting
    {
        Gate::forUser($actor)->authorize('update', $meeting);

        return DB::transaction(function () use ($meeting, $data, $actor) {
            $meeting = SupervisionMeeting::query()->lockForUpdate()->findOrFail($meeting->id);
            Gate::forUser($actor)->authorize('update', $meeting);

            $changes = $data;
            if (isset($data['scheduled_at']) || isset($data['duration_minutes'])) {
                $start = Carbon::parse($data['scheduled_at'] ?? $meeting->scheduled_at);
                $duration = $data['duration_minutes'] ?? $meeting->duration_minutes;
                $participants = $meeting->participants()->pluck('users.id')->all();
                User::whereKey($participants)->orderBy('id')->lockForUpdate()->get();
                $this->assertNoParticipantConflict(
                    $participants,
                    $start,
                    $start->copy()->addMinutes($duration),
                    $meeting->id
                );
            }

            if (($changes['status'] ?? null) === SupervisionMeeting::STATUS_COMPLETED) {
                $changes['completed_at'] = now();
            } elseif (($changes['status'] ?? null) === SupervisionMeeting::STATUS_SCHEDULED) {
                $changes['completed_at'] = null;
            }

            $oldValues = $meeting->only(array_keys($changes));
            $meeting->update($changes);
            $this->recordHistory($meeting, null, 'meeting_updated', $actor, $oldValues, $meeting->only(array_keys($changes)));

            return $meeting->fresh(['thesis', 'participants', 'actions.assignee', 'creator', 'history.changedBy']);
        });
    }

    public function createAction(
        SupervisionMeeting $meeting,
        array $data,
        User $actor
    ): SupervisionMeetingAction {
        return DB::transaction(function () use ($meeting, $data, $actor) {
            $meeting = SupervisionMeeting::query()->lockForUpdate()->findOrFail($meeting->id);
            Gate::forUser($actor)->authorize('manageActions', $meeting);

            if (!$meeting->participants()->whereKey($data['assigned_to'])->exists()) {
                throw ValidationException::withMessages([
                    'assigned_to' => ['Actions can only be assigned to a meeting participant.'],
                ]);
            }

            $action = $meeting->actions()->create([
                ...$data,
                'created_by' => $actor->id,
                'status' => SupervisionMeetingAction::STATUS_PENDING,
            ]);
            $this->recordHistory(
                $meeting,
                $action->id,
                'action_created',
                $actor,
                null,
                $action->only(['assigned_to', 'description', 'due_at', 'status'])
            );

            return $action->load(['assignee', 'creator']);
        });
    }

    public function updateAction(
        SupervisionMeeting $meeting,
        SupervisionMeetingAction $action,
        array $data,
        User $actor
    ): SupervisionMeetingAction {
        return DB::transaction(function () use ($meeting, $action, $data, $actor) {
            $meeting = SupervisionMeeting::query()->lockForUpdate()->findOrFail($meeting->id);
            Gate::forUser($actor)->authorize('manageActions', $meeting);

            $action = SupervisionMeetingAction::query()
                ->where('supervision_meeting_id', $meeting->id)
                ->lockForUpdate()
                ->findOrFail($action->id);

            if (isset($data['assigned_to']) && !$meeting->participants()->whereKey($data['assigned_to'])->exists()) {
                throw ValidationException::withMessages([
                    'assigned_to' => ['Actions can only be assigned to a meeting participant.'],
                ]);
            }

            if (isset($data['status']) && (int) $action->assigned_to !== (int) $actor->id) {
                throw new \Illuminate\Auth\Access\AuthorizationException();
            }

            $oldValues = $action->only(array_keys($data));
            if (($data['status'] ?? null) === SupervisionMeetingAction::STATUS_COMPLETED) {
                $data['completed_at'] = now();
            } elseif (isset($data['status'])) {
                $data['completed_at'] = null;
            }

            $action->update($data);
            $this->recordHistory(
                $meeting,
                $action->id,
                'action_updated',
                $actor,
                $oldValues,
                $action->only(array_keys($data))
            );

            return $action->fresh(['assignee', 'creator']);
        });
    }

    private function assertNoParticipantConflict(
        array $userIds,
        Carbon $start,
        Carbon $end,
        ?int $exceptMeetingId = null
    ): void {
        $meetings = SupervisionMeeting::query()
            ->where('status', SupervisionMeeting::STATUS_SCHEDULED)
            ->whereHas('participants', fn ($participants) => $participants->whereIn('users.id', $userIds))
            ->when($exceptMeetingId, fn ($query) => $query->where('id', '!=', $exceptMeetingId))
            ->where('scheduled_at', '<', $end)
            ->get(['id', 'scheduled_at', 'duration_minutes']);

        if ($meetings->contains(fn (SupervisionMeeting $meeting) => $meeting->ends_at > $start)) {
            throw ValidationException::withMessages([
                'scheduled_at' => ['A meeting participant already has a meeting during the requested time.'],
            ]);
        }
    }

    private function recordHistory(
        SupervisionMeeting $meeting,
        ?int $actionId,
        string $event,
        User $actor,
        ?array $oldValues,
        ?array $newValues
    ): void {
        SupervisionMeetingHistory::create([
            'supervision_meeting_id' => $meeting->id,
            'action_id' => $actionId,
            'changed_by' => $actor->id,
            'event' => $event,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }
}
