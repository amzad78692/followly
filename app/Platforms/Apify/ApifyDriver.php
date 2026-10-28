<?php

namespace App\Platforms\Apify;

use App\Models\InfluencerAccount;
use App\Platforms\Data\AccountData;
use App\Platforms\Data\PostData;
use App\Platforms\Driver;
use App\Platforms\Exceptions\AccountNotFoundException;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * A platform read through one Apify actor that returns a profile and its recent posts together.
 * Subclasses build the actor input and map its output into the shared DTOs.
 */
abstract class ApifyDriver extends Driver
{
    /** A sync calls fetchProfile and fetchRecentPosts; both reuse one actor run for this long. */
    protected const ITEMS_CACHE_MINUTES = 10;

    public function __construct(protected ApifyClient $client) {}

    abstract protected function input(string $handle, int $limit): array;

    /**
     * The profile from the actor's items, or null when the account does not exist.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    abstract protected function toAccount(array $items): ?AccountData;

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return Collection<int, PostData>
     */
    abstract protected function toPosts(array $items): Collection;

    /**
     * The handle in a profile or post link, or null when the link has none.
     */
    abstract protected function handleFromUrl(string $url): ?string;

    protected function lookup(string $urlOrHandle): ?AccountData
    {
        $handle = $this->isUrl($urlOrHandle) ? $this->handleFromUrl($urlOrHandle) : $this->cleanHandle($urlOrHandle);

        return $handle ? $this->toAccount($this->items($handle)) : null;
    }

    public function fetchProfile(InfluencerAccount $account): AccountData
    {
        return $this->toAccount($this->items($account->handle))
            ?? throw AccountNotFoundException::for($this->platform(), $account->handle);
    }

    public function fetchRecentPosts(InfluencerAccount $account, ?CarbonInterface $since = null): Collection
    {
        return $this->toPosts($this->items($account->handle))
            ->filter(fn (PostData $post) => ! $since || $post->publishedAt->gt($since))
            ->sortByDesc(fn (PostData $post) => $post->publishedAt)
            ->values();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function items(string $handle): array
    {
        $handle = ltrim($handle, '@');
        $limit = config('platforms.posts_per_fetch');
        $key = 'platforms:apify:'.$this->platform()->value.':'.Str::lower($handle).':'.$limit;

        return Cache::remember($key, now()->addMinutes(static::ITEMS_CACHE_MINUTES), function () use ($handle, $limit) {
            $this->throttle();

            return $this->client->run($this->platform(), $this->actor(), $this->input($handle, $limit));
        });
    }

    protected function actor(): string
    {
        return config('services.apify.actors.'.$this->platform()->value);
    }

    /**
     * Integer metrics with missing values dropped.
     *
     * @return array<string, int>
     */
    protected function metrics(array $values): array
    {
        return array_map('intval', array_filter($values, fn ($value) => is_numeric($value)));
    }
}
