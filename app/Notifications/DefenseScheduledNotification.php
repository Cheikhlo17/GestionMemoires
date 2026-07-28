<?php

namespace App\Notifications;

use App\Models\DefenseSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DefenseScheduledNotification extends Notification
{
    use Queueable;

    public function __construct(protected DefenseSchedule $schedule)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $thesis = $this->schedule->thesis;

        return (new MailMessage())
            ->subject('Thesis Defense Scheduled')
            ->greeting("Hello {$notifiable->first_name},")
            ->line("A defense has been scheduled for the thesis \"{$thesis->title}\".")
            ->line('Date/Time: ' . $this->schedule->scheduled_at->format('F j, Y g:i A'))
            ->line('Room: ' . $this->schedule->room->name)
            ->action('View Details', config('app.frontend_url') . "/defenses/{$this->schedule->id}");
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'defense_scheduled',
            'defense_schedule_id' => $this->schedule->id,
            'thesis_id' => $this->schedule->thesis_id,
            'scheduled_at' => $this->schedule->scheduled_at->toIso8601String(),
            'message' => 'A thesis defense has been scheduled.',
        ];
    }
}