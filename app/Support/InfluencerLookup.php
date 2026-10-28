<?php

namespace App\Support;

use App\Enums\Platform;
use App\Models\Influencer;
use App\Models\InfluencerAccount;
use App\Platforms\AccountFinder;
use App\Platforms\Data\AccountData;
use App\Platforms\Exceptions\PlatformException;
use App\Platforms\PlatformManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Finds a creator from a pasted profile link, a handle or a name: in the database first,
 * then through the platform drivers, along with their accounts on the other platforms.
 */
class InfluencerLookup
{
    public function __construct(protected PlatformManager $platforms, protected AccountFinder $finder) {}

    /**
     * @throws PlatformException when the link's platform cannot be reached
     */
    public function lookup(string $query): LookupResult
    {
        if ($influencer = $this->find($query)) {
            return new LookupResult($influencer);
        }

        if (! $primary = $this->platforms->resolve($query)) {
            return new LookupResult;
        }

        $found = collect([$primary->platform->value => $primary])->merge($this->finder->discover($primary));
        $stored = $this->storedAccounts($found);

        // A known account (its handle may have changed) means the creator is already here.
        $influencer = ($stored->get($primary->platform->value) ?? $stored->first())?->influencer;
        $taken = $influencer ? $influencer->accounts->pluck('platform')->map->value : collect();

        return new LookupResult($influencer, $found
            ->reject(fn (AccountData $account, string $platform) => $stored->has($platform) || $taken->contains($platform)));
    }

    /**
     * Find an influencer that is already in the database.
     */
    public function find(string $query): ?Influencer
    {
        $query = trim($query);

        if ($query === '') {
            return null;
        }

        $platform = $this->looksLikeUrl($query) ? Platform::fromUrl($query) : null;

        if ($this->looksLikeUrl($query) && ! $platform) {
            return null;
        }

        $account = $platform
            ? $this->findAccountFromUrl($platform, $query)
            : $this->findAccountByHandle(ltrim($query, '@'));

        if ($account) {
            return $account->influencer;
        }

        // Plain text that is not a known handle: try the creator's name.
        if (! $platform) {
            return Influencer::where('slug', Str::slug($query))
                ->orWhere('name', 'like', $query)
                ->first();
        }

        return null;
    }

    /**
     * Stored accounts matching the found ones by platform id, keyed by platform.
     *
     * @param  Collection<string, AccountData>  $found
     * @return Collection<string, InfluencerAccount>
     */
    protected function storedAccounts(Collection $found): Collection
    {
        return InfluencerAccount::with('influencer.accounts')
            ->where(fn ($q) => $found->each(fn (AccountData $account) => $q->orWhere(fn ($q) => $q
                ->where('platform', $account->platform)
                ->where('external_id', $account->externalId))))
            ->whereHas('influencer')
            ->get()
            ->keyBy(fn (InfluencerAccount $account) => $account->platform->value);
    }

    protected function findAccountFromUrl(Platform $platform, string $url): ?InfluencerAccount
    {
        $path = trim(parse_url(str_contains($url, '://') ? $url : "https://{$url}", PHP_URL_PATH) ?? '', '/');
        $segments = array_values(array_filter(explode('/', $path)));

        if ($segments === []) {
            return null;
        }

        // youtube.com/channel/UC... carries the channel id rather than a handle.
        if ($platform === Platform::YouTube && $segments[0] === 'channel' && isset($segments[1])) {
            return InfluencerAccount::where('platform', $platform)->where('external_id', $segments[1])->first();
        }

        $handle = $platform === Platform::YouTube && in_array($segments[0], ['c', 'user']) && isset($segments[1])
            ? $segments[1]
            : $segments[0];

        return $this->findAccountByHandle(ltrim($handle, '@'), $platform);
    }

    protected function findAccountByHandle(string $handle, ?Platform $platform = null): ?InfluencerAccount
    {
        if ($handle === '' || str_contains($handle, ' ')) {
            return null;
        }

        $handle = Str::lower($handle);

        return InfluencerAccount::query()
            ->when($platform, fn ($q) => $q->where('platform', $platform))
            ->where(fn ($q) => $q
                ->whereRaw('lower(handle) = ?', [$handle])
                ->orWhereRaw('lower(handle) = ?', ['@'.$handle]))
            ->whereHas('influencer')
            ->first();
    }

    protected function looksLikeUrl(string $query): bool
    {
        return str_contains($query, '/') || preg_match('/^[\w-]+(\.[\w-]+)+$/', $query) === 1;
    }
}
