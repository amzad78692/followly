<?php

namespace App\Support;

use App\Enums\Platform;
use App\Models\Influencer;
use App\Models\InfluencerAccount;
use Illuminate\Support\Str;

/**
 * Finds an existing influencer from a pasted profile link, a handle or a name.
 * Phase 5 adds the platform drivers for creators that are not in the database yet.
 */
class InfluencerLookup
{
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
