<?php

namespace Tests\Fakes;

use App\Enums\Platform;
use App\Models\InfluencerAccount;
use App\Platforms\Data\AccountData;
use App\Platforms\Driver;
use App\Platforms\Exceptions\PlatformException;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * A driver that answers from an in-memory list of profiles, keyed by lowercase handle.
 */
class FakeDriver extends Driver
{
    /** @var array<int, string> */
    public array $lookups = [];

    public bool $fails = false;

    /**
     * @param  array<string, AccountData>  $accounts
     */
    public function __construct(protected Platform $platform, protected array $accounts = []) {}

    public static function with(Platform $platform, AccountData ...$accounts): static
    {
        return new static($platform, collect($accounts)->keyBy(fn (AccountData $account) => strtolower($account->handle))->all());
    }

    public function platform(): Platform
    {
        return $this->platform;
    }

    protected function lookup(string $urlOrHandle): ?AccountData
    {
        $this->lookups[] = $urlOrHandle;

        if ($this->fails) {
            throw new PlatformException($this->platform, 'service unavailable');
        }

        $handle = $this->isUrl($urlOrHandle) ? ($this->segments($urlOrHandle)[0] ?? '') : $urlOrHandle;

        return $this->accounts[strtolower(ltrim($handle, '@'))] ?? null;
    }

    public function fetchProfile(InfluencerAccount $account): AccountData
    {
        return $this->accounts[strtolower(ltrim($account->handle, '@'))];
    }

    public function fetchRecentPosts(InfluencerAccount $account, ?CarbonInterface $since = null): Collection
    {
        return collect();
    }
}
