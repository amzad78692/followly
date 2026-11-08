<?php

namespace App\Listeners;

use App\Events\PostsSynced;
use App\Jobs\SendPush;
use App\Models\Follow;
use App\Notifications\NewPostNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Tell everyone who tracks the account about its new posts. Posts older than max_post_age_hours
 * (a first sync stores the account's back catalogue) and posts from before the user followed
 * are left out, so only genuinely new posts notify. Users who muted the platform, or turned off
 * both in-app alerts and the digest, are skipped.
 */
class NotifyFollowersOfNewPosts implements ShouldQueue
{
    public function handle(PostsSynced $event): void
    {
        $cutoff = now()->subHours(config('notifications.max_post_age_hours'));

        $posts = $event->posts
            ->filter(fn ($post) => $post->published_at?->gte($cutoff))
            ->sortByDesc('published_at')
            ->values();

        if ($posts->isEmpty()) {
            return;
        }

        Follow::query()
            ->whereHas('accounts', fn (Builder $q) => $q->whereKey($event->account->id))
            ->with(['user' => fn ($q) => $q->withExists('deviceTokens')])
            ->chunkById(500, function ($follows) use ($event, $posts) {
                foreach ($follows as $follow) {
                    if (! $follow->user->wantsPostNotifications() || ! $follow->user->wantsPostsFrom($event->account->platform)) {
                        continue;
                    }

                    $since = $posts->filter(fn ($post) => $post->published_at->gt($follow->created_at))->values();

                    if ($since->isNotEmpty()) {
                        $this->notify($follow, new NewPostNotification($event->account, new Collection($since->all())));
                    }
                }
            });
    }

    /**
     * Store the update and push it. With alerts off for this creator or for push altogether, the
     * update still lands in the feed and the watch gets a silent refresh.
     */
    protected function notify(Follow $follow, NewPostNotification $notification): void
    {
        $user = $follow->user;

        // Chosen up front because the push opens this notification on the watch.
        $notification->id = (string) Str::uuid();
        $user->notify($notification);

        if (! $user->device_tokens_exists) {
            return;
        }

        $update = $notification->toArray($user);
        $alert = $user->notify_push && $follow->notify;

        SendPush::dispatch(
            $user->id,
            $alert ? $update['influencer_name'] : null,
            $alert ? ($update['count'] > 1 ? "{$update['count']} new posts" : ($update['title'] ?: 'New post')) : null,
            ['type' => 'new-post', 'notification_id' => $notification->id, 'creator_slug' => $update['influencer_slug']],
        );
    }
}
