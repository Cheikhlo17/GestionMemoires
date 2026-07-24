<?php

namespace App\Listeners;

use App\Events\ThesisStatusChanged;
use App\Notifications\ThesisStatusChangedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyStudentOfStatusChange implements ShouldQueue
{
    public function handle(ThesisStatusChanged $event): void
    {
        $studentUser = $event->thesis->student->user;

        $studentUser->notify(
            new ThesisStatusChangedNotification($event->thesis, $event->toStatus, $event->remarks)
        );
    }
}