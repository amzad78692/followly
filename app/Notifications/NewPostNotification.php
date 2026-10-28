<?php

namespace App\Notifications;

use App\Models\InfluencerAccount;
use App\Models\Post;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * One sync stored new posts from an account the user tracks. Several posts from the same
 * sync become one notification, so a creator posting three times is not three alerts.
 */
class NewPostNotification extends Notification
{
    /**
     * @param  Collection<int, Post>  $posts  newest first
     */
    public function __construct(public InfluencerAccount $account, public Collection $posts) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return 'new-post';
    }

    public function toArray(object $notifiable): array
    {
        $influencer = $this->account->influencer;
        $latest = $this->posts->first();

        return [
            'influencer_id' => $influencer->id,
            'influencer_name' => $influencer->name,
            'influencer_slug' => $influencer->slug,
            'avatar' => $influencer->avatar,
            'platform' => $this->account->platform->value,
            'post_ids' => $this->posts->modelKeys(),
            'count' => $this->posts->count(),
            'title' => Str::limit($latest->title ?: (string) $latest->body, 120),
            'image' => $latest->images[0] ?? null,
            'permalink' => $latest->permalink,
            'published_at' => $latest->published_at?->toIso8601String(),
        ];
    }
}
