<?php

namespace App\Discovery;

use App\Models\Country;
use App\Models\Influencer;
use App\Models\Post;
use App\Models\Taxonomy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * The top bar search and the /search page: influencers, posts, categories and countries.
 * Categories and countries only match when at least one influencer is in them.
 */
class GlobalSearch
{
    /**
     * Whether the term is long enough to search for.
     */
    public static function searchable(?string $term): bool
    {
        return Str::length(trim((string) $term)) >= config('discovery.search.min_length');
    }

    /**
     * Influencers whose name, handle, category or country matches.
     *
     * @return Collection<int, Influencer>
     */
    public function influencers(string $term, int $limit): Collection
    {
        return Influencer::query()
            ->with(['category', 'country', 'accounts'])
            ->where(fn (Builder $q) => $q
                ->search(trim($term))
                ->orWhereHas('category', fn (Builder $q) => $q->where('name', 'like', '%'.trim($term).'%'))
                ->orWhereHas('country', fn (Builder $q) => $q->where('name', 'like', '%'.trim($term).'%')))
            ->mostTracked()
            ->limit($limit)
            ->get();
    }

    /**
     * Every result type at once, grouped: influencers, posts, categories and countries.
     *
     * @return array<string, Collection>
     */
    public function grouped(string $term, int $limit): array
    {
        return [
            'influencers' => $this->influencers($term, $limit),
            'posts' => $this->posts($term, $limit),
            'categories' => $this->categories($term, $limit),
            'countries' => $this->countries($term, $limit),
        ];
    }

    /**
     * Visible posts whose caption or title matches, newest first.
     * shortcut: LIKE scan (16ms on local data), add a MySQL full-text index when posts grow past ~100k.
     *
     * @return Collection<int, Post>
     */
    public function posts(string $term, int $limit): Collection
    {
        $like = '%'.trim($term).'%';

        return Post::query()
            ->visible()
            ->with('influencer')
            ->where(fn (Builder $q) => $q->where('body', 'like', $like)->orWhere('title', 'like', $like))
            ->latest('published_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, Taxonomy> with influencers_count
     */
    public function categories(string $term, int $limit): Collection
    {
        return Taxonomy::query()
            ->where('type', 'category')
            ->where('name', 'like', '%'.trim($term).'%')
            ->whereHas('influencers')
            ->withCount('influencers')
            ->orderByDesc('influencers_count')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, Country> with influencers_count
     */
    public function countries(string $term, int $limit): Collection
    {
        return Country::query()
            ->where(fn (Builder $q) => $q
                ->where('name', 'like', '%'.trim($term).'%')
                ->orWhere('iso2', trim($term)))
            ->whereHas('influencers')
            ->withCount('influencers')
            ->orderByDesc('influencers_count')
            ->limit($limit)
            ->get();
    }
}
