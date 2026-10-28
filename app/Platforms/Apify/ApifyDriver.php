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

    /**
     * Actor input for one or more handles (without "@"), $limit posts each.
     *
     * @param  array<int, string>  $handles
     */
    abstract protected function input(array $handles, int $limit): array;

    /**
     * Which handle an output item belongs to, so a batched run can be split per account.
     */
    abstract protected function handleOf(array $item): ?string;

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
        return $this->newestFirst($this->toPosts($this->items($account->handle)))
            ->filter(fn (PostData $post) => ! $since || $post->publishedAt->gt($since))
            ->values();
    }

    /**
     * Profiles and recent posts for many accounts in one actor run.
     *
     * @param  Collection<int, InfluencerAccount>  $accounts
     *                                                        Keyed by account id; the profile is null when the account was not found.
     * @return Collection<int, array{profile: ?AccountData, posts: Collection<int, PostData>}>
     */
    public function fetchMany(Collection $accounts): Collection
    {
        $handles = $accounts->map(fn (InfluencerAccount $account) => $this->key($account->handle))->unique();

        // Handles looked up in the last few minutes (e.g. on the Add page) need no new run.
        $items = $handles->mapWithKeys(fn (string $handle) => [$handle => Cache::get($this->cacheKey($handle))])->filter()->all();
        $missing = $handles->reject(fn (string $handle) => isset($items[$handle]))->values()->all();

        if ($missing) {
            $items += $this->run($missing);
        }

        return $accounts->mapWithKeys(function (InfluencerAccount $account) use ($items) {
            $own = $items[$this->key($account->handle)] ?? [];

            return [$account->id => ['profile' => $this->toAccount($own), 'posts' => $this->newestFirst($this->toPosts($own))]];
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function items(string $handle): array
    {
        $handle = $this->key($handle);

        return Cache::remember($this->cacheKey($handle), now()->addMinutes(static::ITEMS_CACHE_MINUTES), fn () => $this->run([$handle])[$handle] ?? []);
    }

    protected function cacheKey(string $handle): string
    {
        return 'platforms:apify:'.$this->platform()->value.':'.$handle.':'.config('platforms.posts_per_fetch');
    }

    /**
     * Run the actor and group its items by lowercase handle.
     *
     * @param  array<int, string>  $handles
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function run(array $handles): array
    {
        $this->throttle();

        $items = $this->client->run($this->platform(), $this->actor(), $this->input($handles, config('platforms.posts_per_fetch')));

        return collect($items)
            ->groupBy(fn (array $item) => $this->key((string) $this->handleOf($item)))
            ->forget('')
            ->map->all()
            ->all();
    }

    protected function key(string $handle): string
    {
        return Str::lower(ltrim($handle, '@'));
    }

    /**
     * @param  Collection<int, PostData>  $posts
     * @return Collection<int, PostData>
     */
    protected function newestFirst(Collection $posts): Collection
    {
        return $posts->sortByDesc(fn (PostData $post) => $post->publishedAt)->values();
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
