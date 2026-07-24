<?php

namespace App\Listeners;

use App\Events\ThesisCommented;
use App\Notifications\NewThesisCommentNotification;
use Illuminate\Contracts\Queue\ShouldQueue;


class NotifyParticipantsOfComment implements ShouldQueue
{
    public function handle(ThesisCommented $event): void
    {
        $thesis = $event->comment->thesis;
        $author = $event->comment->author;

        $recipients = collect([$thesis->student->user, $thesis->supervisor?->user])
            ->filter()
            ->reject(fn ($user) => $user->id === $author->id)
            ->unique('id');

        foreach ($recipients as $recipient) {
            $recipient->notify(new NewThesisCommentNotification($event->comment));
        }
    }
}