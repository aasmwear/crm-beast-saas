<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class NewActivity extends Notification
{
    use Queueable;

    /** @param array<string, mixed> $payload */
    public function __construct(public array $payload = []) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database']; // add 'mail' when SMTP is ready
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->payload['subject'] ?? 'Activity')
            ->line($this->payload['message'] ?? 'New activity');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return $this->payload;
    }
}
