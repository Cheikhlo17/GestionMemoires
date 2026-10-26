<?php

namespace App\Services\Interfaces;

use App\Models\SupervisionMeeting;
use App\Models\SupervisionMeetingAction;
use App\Models\Thesis;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SupervisionMeetingServiceInterface
{
    public function list(User $viewer, array $filters): LengthAwarePaginator;

    public function create(Thesis $thesis, array $data, User $actor): SupervisionMeeting;

    public function update(SupervisionMeeting $meeting, array $data, User $actor): SupervisionMeeting;

    public function createAction(SupervisionMeeting $meeting, array $data, User $actor): SupervisionMeetingAction;

    public function updateAction(
        SupervisionMeeting $meeting,
        SupervisionMeetingAction $action,
        array $data,
        User $actor
    ): SupervisionMeetingAction;
}
