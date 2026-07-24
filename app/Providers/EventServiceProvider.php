<?php

namespace App\Providers;

use App\Events\ThesisCommented;
use App\Events\ThesisStatusChanged;
use App\Events\ThesisSubmitted;
use App\Listeners\NotifyParticipantsOfComment;
use App\Listeners\NotifyStudentOfStatusChange;
use App\Listeners\NotifySupervisorOfSubmission;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        ThesisSubmitted::class => [
            NotifySupervisorOfSubmission::class,
        ],
        ThesisStatusChanged::class => [
            NotifyStudentOfStatusChange::class,
        ],
        ThesisCommented::class => [
            NotifyParticipantsOfComment::class,
        ],
    ];

    public function boot(): void
    {
    }
}