<?php

namespace App\Feed;

use App\Enums\Platform;
use App\Models\Collection;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The Dashboard's Smart Feed (Phase 0 ranking, no AI). A post's score is its publish time,
 * moved later by a boost for Watchlist influencers and a smaller one when its likes and comments
 * are well above the account's average. A SmartLink group shows once, as its first post.
 */
class SmartFeed
{
    public const WATCHLIST_BOOST_HOURS = 12;

    public const ENGAGEMENT_BOOST_HOURS = 6;

    /** Engagement counts as high from this many times the account's average. */
    public const ENGAGEMENT_FACTOR = 2;

    /**
     * Posts from the accounts the user tracks, or from a collection's influencers, best first.
     */
    public function query(User $user, ?Platform $platform = null, bool $watchlist = false, ?Collection $collection = null): Builder
    {
        $watchlistIds = $user->follows()->whereNotNull('watchlisted_at')->pluck('influencer_id');

        $base = fn () => Post::query()
            ->visible()
            ->when($collection,
                fn (Builder $q) => $q->whereIn('influencer_id', DB::table('collection_influencer')->where('collection_id', $collection->id)->select('influencer_id')),
                fn (Builder $q) => $q->feedFor($user)->reorder())
            ->when($watchlist, fn (Builder $q) => $q->whereIn('influencer_id', $watchlistIds))
            ->platform($platform);

        $usual = DB::table('posts')
            ->whereIn('influencer_account_id', $base()->select('influencer_account_id'))
            ->groupBy('influencer_account_id')
            ->selectRaw('influencer_account_id as usual_account_id, avg('.$this->engagement('posts').') as usual_engagement');

        $watchlistBoost = $watchlistIds->isEmpty() ? '0' : sprintf(
            'case when posts.influencer_id in (%s) then %d else 0 end',
            $watchlistIds->map(fn ($id) => (int) $id)->implode(','),
            self::WATCHLIST_BOOST_HOURS * 3600,
        );

        $engagementBoost = sprintf(
            'case when usual.usual_engagement > 0 and %s >= usual.usual_engagement * %d then %d else 0 end',
            $this->engagement('posts'), self::ENGAGEMENT_FACTOR, self::ENGAGEMENT_BOOST_HOURS * 3600,
        );

        return $base()
            ->leftJoinSub($usual, 'usual', 'usual.usual_account_id', '=', 'posts.influencer_account_id')
            ->where(fn (Builder $q) => $q
                ->whereNull('posts.group_id')
                ->orWhereIn('posts.id', $base()->whereNotNull('group_id')->groupBy('group_id')->selectRaw('min(id)')))
            ->select('posts.*')
            ->selectRaw("{$this->epoch('posts.published_at')} + {$watchlistBoost} + {$engagementBoost} as score")
            ->orderByDesc('score')
            ->orderByDesc('posts.id');
    }

    protected function engagement(string $table): string
    {
        return collect(['likes', 'comments'])
            ->map(fn ($metric) => "coalesce(cast(json_extract({$table}.metrics, '$.{$metric}') as decimal(20)), 0)")
            ->implode(' + ');
    }

    protected function epoch(string $column): string
    {
        return DB::getDriverName() === 'sqlite' ? "cast(strftime('%s', {$column}) as integer)" : "unix_timestamp({$column})";
    }
}
