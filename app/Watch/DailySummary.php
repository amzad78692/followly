<?php

namespace App\Watch;

use App\Models\User;

class DailySummary
{
    /**
     * Today's new posts for the user, where "today" starts at midnight in the given timezone.
     *
     * @return array{posts: int, creators: int, avatars: list<array{name: string, avatar: ?string}>}
     */
    public static function for(User $user, ?string $timezone = null): array
    {
        $updates = $user->notifications()
            ->where('type', 'new-post')
            ->where('created_at', '>=', now($timezone)->startOfDay()->setTimezone(config('app.timezone')))
            ->get()
            ->pluck('data');

        $creators = $updates->unique('influencer_id');

        return [
            'posts' => (int) $updates->sum(fn (array $update) => $update['count'] ?? 1),
            'creators' => $creators->count(),
            'avatars' => $creators->take(3)->map(fn (array $update) => [
                'name' => $update['influencer_name'],
                'avatar' => image_url($update['avatar'] ?? null, 96, 96),
            ])->values()->all(),
        ];
    }
}
