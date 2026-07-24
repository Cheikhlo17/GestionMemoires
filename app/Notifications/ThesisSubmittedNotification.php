<?php

namespace App\Notifications;

use App\Models\Thesis;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ThesisSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(protected Thesis $thesis)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('New Thesis Submitted for Review')
            ->greeting("Hello {$notifiable->first_name},")
            ->line("A new thesis titled \"{$this->thesis->title}\" has been submitted for your review.")
            ->action('Review Thesis', config('app.frontend_url') . "/theses/{$this->thesis->id}")
            ->line('Please review it at your earliest convenience.');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'thesis_submitted',
            'thesis_id' => $this->thesis->id,
            'title' => $this->thesis->title,
            'message' => "A new thesis \"{$this->thesis->title}\" was submitted for your review.",
        ];
    }
}