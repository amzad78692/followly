<?php

namespace App\Listeners;

use App\Events\PostsSynced;
use App\Models\Follow;
use App\Notifications\NewPostNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

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
            ->with('user')
            ->chunkById(500, function ($follows) use ($event, $posts) {
                foreach ($follows as $follow) {
                    if (! $follow->user->wantsPostNotifications() || ! $follow->user->wantsPostsFrom($event->account->platform)) {
                        continue;
                    }

                    $since = $posts->filter(fn ($post) => $post->published_at->gt($follow->created_at))->values();

                    if ($since->isNotEmpty()) {
                        $follow->user->notify(new NewPostNotification($event->account, new Collection($since->all())));
                    }
                }
            });
    }
}
