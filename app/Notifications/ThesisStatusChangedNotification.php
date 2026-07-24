<?php

namespace App\Notifications;

use App\Models\Thesis;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ThesisStatusChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Thesis $thesis,
        protected string $toStatus,
        protected ?string $remarks = null
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $status = str_replace('_', ' ', $this->toStatus);

        $mail = (new MailMessage())
            ->subject('Thesis Status Updated')
            ->greeting("Hello {$notifiable->first_name},")
            ->line("The status of your thesis \"{$this->thesis->title}\" has changed to: {$status}.");

        if ($this->remarks) {
            $mail->line("Remarks: {$this->remarks}");
        }

        return $mail->action('View Thesis', config('app.frontend_url') . "/theses/{$this->thesis->id}");
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'thesis_status_changed',
            'thesis_id' => $this->thesis->id,
            'title' => $this->thesis->title,
            'status' => $this->toStatus,
            'message' => "Your thesis \"{$this->thesis->title}\" status changed to " . str_replace('_', ' ', $this->toStatus) . '.',
        ];
    }
}