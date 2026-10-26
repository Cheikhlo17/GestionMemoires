<?php

namespace App\Policies;

use App\Models\SupervisionMeeting;
use App\Models\User;

class SupervisionMeetingPolicy
{
    public function view(User $user, SupervisionMeeting $meeting): bool
    {
        return $meeting->participants()->whereKey($user->id)->exists();
    }

    public function update(User $user, SupervisionMeeting $meeting): bool
    {
        return $meeting->status === SupervisionMeeting::STATUS_SCHEDULED
            && $this->view($user, $meeting);
    }

    public function manageActions(User $user, SupervisionMeeting $meeting): bool
    {
        return $meeting->status !== SupervisionMeeting::STATUS_CANCELLED
            && $this->view($user, $meeting);
    }
}
