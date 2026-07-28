<?php

namespace App\Notifications;

use App\Models\DefenseSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DefenseUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(protected DefenseSchedule $schedule, protected string $reason)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Thesis Defense Update')
            ->greeting("Hello {$notifiable->first_name},")
            ->line("Your thesis defense schedule has been updated: {$this->reason}")
            ->line('New Date/Time: ' . $this->schedule->scheduled_at->format('F j, Y g:i A'))
            ->line('Status: ' . $this->schedule->status);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'defense_updated',
            'defense_schedule_id' => $this->schedule->id,
            'reason' => $this->reason,
            'message' => "Defense schedule updated: {$this->reason}",
        ];
    }
}