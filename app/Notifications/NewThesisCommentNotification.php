<?php

namespace App\Notifications;

use App\Models\Comment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewThesisCommentNotification extends Notification
{
    use Queueable;

    public function __construct(protected Comment $comment)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('New Comment on Thesis')
            ->greeting("Hello {$notifiable->first_name},")
            ->line("{$this->comment->author->full_name} commented on \"{$this->comment->thesis->title}\":")
            ->line($this->comment->content)
            ->action('View Thesis', config('app.frontend_url') . "/theses/{$this->comment->thesis_id}");
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'new_comment',
            'thesis_id' => $this->comment->thesis_id,
            'comment_id' => $this->comment->id,
            'author' => $this->comment->author->full_name,
            'message' => "{$this->comment->author->full_name} commented on your thesis.",
        ];
    }
}