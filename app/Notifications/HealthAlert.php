<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * An operations email to admins: failed queue jobs, a high sync error rate or a failed backup.
 * Sent straight away rather than queued, since the queue may be what is broken.
 */
class HealthAlert extends Notification
{
    use Queueable;

    /**
     * @param  array<int, string>  $lines
     */
    public function __construct(public string $subject, public array $lines) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return collect($this->lines)->reduce(fn (MailMessage $mail, string $line) => $mail->line($line), (new MailMessage)->subject($this->subject));
    }
}
