<?php

namespace App\Notifications;

use App\Models\Post;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Followly Alerts: a new post mentions one of the user's keywords. Same data keys as
 * NewPostNotification, so the bell, digest and watch show it without special cases.
 */
class KeywordAlertNotification extends Notification
{
    /**
     * @param  list<string>  $keywords  the user's keywords the post matched
     */
    public function __construct(public Post $post, public array $keywords) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return 'keyword-alert';
    }

    public function toArray(object $notifiable): array
    {
        $influencer = $this->post->influencer;

        return [
            'post_id' => $this->post->id,
            'keywords' => $this->keywords,
            'influencer_id' => $influencer->id,
            'influencer_name' => $influencer->name,
            'influencer_slug' => $influencer->slug,
            'avatar' => $influencer->avatar,
            'platform' => $this->post->platform->value,
            'count' => 1,
            'title' => Str::limit($this->post->title ?: (string) $this->post->body, 120),
            'image' => $this->post->images[0] ?? null,
            'permalink' => $this->post->permalink,
            'published_at' => $this->post->published_at?->toIso8601String(),
        ];
    }
}
