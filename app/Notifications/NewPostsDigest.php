<?php

namespace App\Notifications;

use App\Enums\DigestFrequency;
use App\Enums\Platform;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Email round-up of the unread new post notifications since the last digest.
 */
class NewPostsDigest extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, DatabaseNotification>  $items  newest first
     */
    public function __construct(public DigestFrequency $frequency, public Collection $items, public int $total) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $period = $this->frequency === DigestFrequency::Daily ? 'today' : 'this week';

        $message = (new MailMessage)
            ->subject($this->posts().' new '.Str::plural('post', $this->posts()).' from creators you follow')
            ->greeting("Hi {$notifiable->name},")
            ->line("Here's what the creators you follow posted {$period}:");

        foreach ($this->items as $item) {
            $message->line('**'.e($item->data['influencer_name']).'** on '.Platform::from($item->data['platform'])->label().': '.e(self::summary($item->data)));
        }

        if ($this->total > $this->items->count()) {
            $message->line('…and '.($this->total - $this->items->count()).' more.');
        }

        return $message
            ->action('Open your notifications', route('notifications'))
            ->line('You get this email because you turned on the '.strtolower($this->frequency->label()).' digest.');
    }

    /**
     * Number of posts across the listed notifications.
     */
    protected function posts(): int
    {
        return (int) $this->items->sum(fn (DatabaseNotification $item) => $item->data['count'] ?? 1);
    }

    /**
     * "New video title" or "3 new posts, latest: New video title".
     */
    public static function summary(array $data): string
    {
        $title = $data['title'] ?: 'New post';

        if ($data['keywords'] ?? null) {
            return 'Alert for "'.implode('", "', $data['keywords']).'": '.$title;
        }

        return ($data['count'] ?? 1) > 1 ? "{$data['count']} new posts, latest: {$title}" : $title;
    }
}
