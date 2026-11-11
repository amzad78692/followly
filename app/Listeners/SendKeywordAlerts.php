<?php

namespace App\Listeners;

use App\Events\PostsSynced;
use App\Jobs\SendPush;
use App\Models\KeywordAlert;
use App\Models\Post;
use App\Models\User;
use App\Notifications\KeywordAlertNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;

/**
 * Followly Alerts: after a sync, tell every user whose keywords appear in a new post's caption.
 * One notification per post per user, however many of their keywords match or how often the
 * post comes through. Same rules as new post notifications: back catalogue posts, muted
 * platforms and users with every channel off are skipped.
 */
class SendKeywordAlerts implements ShouldQueue
{
    public function handle(PostsSynced $event): void
    {
        $cutoff = now()->subHours(config('notifications.max_post_age_hours'));

        $posts = $event->posts->filter(fn (Post $post) => $post->published_at?->gte($cutoff))->values();

        if ($posts->isEmpty()) {
            return;
        }

        $posts->load('influencer');

        // Switched-off keywords and users with Alerts off on the Alerts page are skipped.
        KeywordAlert::query()->where('enabled', true)
            ->whereHas('user', fn ($query) => $query->where('alerts_enabled', true))
            ->orderBy('user_id')->get(['user_id', 'keyword'])->groupBy('user_id')
            ->chunk(200)
            ->each(function ($chunk) use ($posts, $event) {
                $users = User::whereKey($chunk->keys())->withExists('deviceTokens')->get();

                foreach ($users as $user) {
                    if (! $user->wantsPostsFrom($event->account->platform) || ! $user->wantsPostNotifications()) {
                        continue;
                    }

                    $keywords = $chunk[$user->id]->pluck('keyword');

                    foreach ($posts as $post) {
                        $matched = $keywords->filter(fn ($keyword) => self::matches($keyword, $post->title.' '.$post->body))->values()->all();

                        if ($matched) {
                            $this->notify($user, $post, $matched);
                        }
                    }
                }
            });
    }

    /**
     * Whole word (or phrase), case-insensitive: "cat" matches "Cat!" but not "category".
     * Lookarounds instead of \b so Arabic and other non-Latin keywords work too.
     */
    public static function matches(string $keyword, string $text): bool
    {
        $keyword = trim($keyword);

        return $keyword !== '' && preg_match('/(?<![\p{L}\p{N}_])'.preg_quote($keyword, '/').'(?![\p{L}\p{N}_])/iu', $text) === 1;
    }

    protected function notify(User $user, Post $post, array $keywords): void
    {
        $already = $user->notifications()->where('type', 'keyword-alert')->where('data->post_id', $post->id)->exists();

        if ($already) {
            return;
        }

        $notification = new KeywordAlertNotification($post, $keywords);
        $notification->id = (string) Str::uuid();
        $user->notify($notification);

        if ($user->device_tokens_exists && $user->notify_push) {
            SendPush::dispatch(
                $user->id,
                "Alert: {$keywords[0]}",
                $post->influencer->name.': '.Str::limit($post->title ?: (string) $post->body, 80),
                ['type' => 'keyword-alert', 'notification_id' => $notification->id, 'creator_slug' => $post->influencer->slug],
            );
        }
    }
}
