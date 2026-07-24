<?php

namespace App\Listeners;

use App\Events\ThesisSubmitted;
use App\Notifications\ThesisSubmittedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifySupervisorOfSubmission implements ShouldQueue
{
    public function handle(ThesisSubmitted $event): void
    {
        $supervisorUser = $event->thesis->supervisor?->user;

        $supervisorUser?->notify(new ThesisSubmittedNotification($event->thesis));
    }
}