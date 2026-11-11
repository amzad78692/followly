<?php

namespace App\Analytics;

use App\Enums\Platform;
use App\Enums\PostType;
use App\Models\Post;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Post statistics for one or more creators over the last N days, compared with the N days before.
 * Loads the posts of both periods in one query; every section is worked out from that.
 */
class CreatorReport
{
    public const RANGES = [7, 30, 90];

    public const METRICS = ['views', 'likes', 'comments'];

    public readonly Carbon $start;

    public readonly Carbon $end;

    /** Posts in the current period, newest first. */
    public readonly Collection $posts;

    /** Posts in the previous period of the same length. */
    public readonly Collection $previous;

    public function __construct(array $influencerIds, public readonly int $days = 30, ?Carbon $end = null)
    {
        $this->end = ($end ?? now())->copy();
        $this->start = $this->end->copy()->subDays($days);

        $all = Post::query()->visible()
            ->whereIn('influencer_id', $influencerIds)
            ->where('published_at', '>', $this->start->copy()->subDays($days))
            ->where('published_at', '<=', $this->end)
            ->latest('published_at')
            ->get();

        [$this->posts, $this->previous] = $all->partition(fn (Post $post) => $post->published_at->gt($this->start))->map->values();
    }

    /**
     * Percent change from $previous to $current, or null when there is nothing to compare with.
     */
    public static function change(int|float $current, int|float $previous): ?float
    {
        return $previous ? round(($current - $previous) / $previous * 100, 1) : null;
    }

    public static function sum(Collection $posts, string $metric): int
    {
        return (int) $posts->sum(fn (Post $post) => $post->metrics[$metric] ?? 0);
    }

    /**
     * Whether any post in either period reports shares (only some platforms do).
     */
    public function hasShares(): bool
    {
        return $this->posts->concat($this->previous)->contains(fn (Post $post) => isset($post->metrics['shares']));
    }

    /**
     * KPI tiles: [key => [label, icon, value, change]]. Shares only when the data exists.
     */
    public function kpis(): array
    {
        $tiles = ['posts' => ['New Posts', 'document-text']] + [
            'views' => ['Total Views', 'eye'],
            'likes' => ['Total Likes', 'heart'],
            'comments' => ['Comments', 'chat-bubble-left'],
        ] + ($this->hasShares() ? ['shares' => ['Shares', 'share']] : []);

        return collect($tiles)->map(function ($tile, $key) {
            [$now, $before] = $key === 'posts'
                ? [$this->posts->count(), $this->previous->count()]
                : [self::sum($this->posts, $key), self::sum($this->previous, $key)];

            return ['label' => $tile[0], 'icon' => $tile[1], 'value' => $now, 'change' => self::change($now, $before)];
        })->all();
    }

    /**
     * One value per day from the start date to today, oldest first: [Y-m-d => total of $metric].
     */
    public function daily(string $metric): array
    {
        $totals = $this->posts->groupBy(fn (Post $post) => $post->published_at->toDateString())
            ->map(fn ($posts) => self::sum($posts, $metric));

        return collect(range($this->days, 0))
            ->mapWithKeys(fn ($ago) => [$date = $this->end->copy()->subDays($ago)->toDateString() => $totals[$date] ?? 0])
            ->all();
    }

    /**
     * Posts per weekday (rows Mon–Sun, 1–7) and week (columns, oldest first, keyed by the Monday).
     *
     * @return array<string, array<int, int>> [Y-m-d of Monday => [1 => count, …, 7 => count]]
     */
    public function heatmap(): array
    {
        $counts = $this->posts->countBy(fn (Post $post) => $post->published_at->copy()->startOfWeek()->toDateString().'/'.$post->published_at->dayOfWeekIso);
        $weeks = [];

        for ($week = $this->start->copy()->startOfWeek(); $week->lte($this->end); $week->addWeek()) {
            $key = $week->toDateString();
            $weeks[$key] = collect(range(1, 7))->mapWithKeys(fn ($day) => [$day => $counts["{$key}/{$day}"] ?? 0])->all();
        }

        return $weeks;
    }

    /**
     * Posts per platform, busiest first: [['platform' => Platform, 'count' => n, 'percent' => p]].
     */
    public function platforms(): Collection
    {
        $total = max($this->posts->count(), 1);

        return $this->posts->countBy(fn (Post $post) => $post->platform->value)
            ->sortDesc()
            ->map(fn ($count, $platform) => ['platform' => Platform::from($platform), 'count' => $count, 'percent' => round($count / $total * 100)])
            ->values();
    }

    /**
     * The format a post counts as in the Content Breakdown.
     */
    public static function format(Post $post): string
    {
        return match ($post->type) {
            PostType::Video => in_array($post->platform, [Platform::TikTok, Platform::Instagram]) || ($post->duration_seconds && $post->duration_seconds <= 180)
                ? 'Shorts/Reels' : 'Videos',
            PostType::Image, PostType::Carousel => 'Images',
            default => 'Other',
        };
    }

    /**
     * [format => count] for every format, in a fixed order.
     */
    public function breakdown(): array
    {
        $counts = $this->posts->countBy(fn (Post $post) => self::format($post));

        return collect(['Videos', 'Shorts/Reels', 'Images', 'Other'])->mapWithKeys(fn ($format) => [$format => $counts[$format] ?? 0])->all();
    }

    public function postsPerWeek(): float
    {
        return round($this->posts->count() / ($this->days / 7), 1);
    }

    /**
     * Weekday name with the most posts, or null with no posts.
     */
    public function mostActiveDay(): ?string
    {
        return $this->posts->countBy(fn (Post $post) => $post->published_at->format('l'))->sortDesc()->keys()->first();
    }

    public function primaryFormat(): ?string
    {
        return collect($this->breakdown())->filter()->sortDesc()->keys()->first();
    }

    /**
     * Most viewed posts of the current period.
     */
    public function best(int $limit = 3): Collection
    {
        return $this->posts->filter(fn (Post $post) => ($post->metrics['views'] ?? 0) > 0)
            ->sortByDesc(fn (Post $post) => $post->metrics['views'])
            ->take($limit)
            ->values();
    }

    /**
     * Per creator: posts, views, engagement (likes + comments) and view change, most viewed first.
     */
    public function perCreator(): Collection
    {
        $before = $this->previous->groupBy('influencer_id');

        return $this->posts->groupBy('influencer_id')->map(fn ($posts, $id) => [
            'influencer_id' => $id,
            'posts' => $posts->count(),
            'views' => $views = self::sum($posts, 'views'),
            'engagement' => self::sum($posts, 'likes') + self::sum($posts, 'comments'),
            'change' => self::change($views, self::sum($before[$id] ?? collect(), 'views')),
        ])->sortByDesc(fn ($row) => [$row['views'], $row['engagement']])->values();
    }
}
