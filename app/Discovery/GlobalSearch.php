<?php

namespace App\Discovery;

use App\Models\Country;
use App\Models\Influencer;
use App\Models\Taxonomy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * The top bar search and the /search page: influencers, categories and countries.
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
