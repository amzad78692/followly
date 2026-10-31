<?php

namespace App\Platforms;

use App\Models\InfluencerAccount;
use App\Platforms\Data\AccountData;
use App\Platforms\Exceptions\PlatformException;
use Illuminate\Support\Collection;

/**
 * From one profile, finds the same creator on the other platforms:
 * first through links in their profiles and bios, then by trying the same handle.
 * Matches by handle are guesses, so the Add page leaves them unticked.
 * Accounts already stored are taken from the database instead of the platform.
 */
class AccountFinder
{
    public function __construct(protected PlatformManager $platforms) {}

    /**
     * @return Collection<string, AccountData> keyed by platform, without the account passed in
     */
    public function discover(AccountData $account): Collection
    {
        $found = collect([$account->platform->value => $account]);

        // Links can chain: a YouTube description links Instagram, whose bio links TikTok.
        $queue = [$account];

        while ($current = array_shift($queue)) {
            foreach ($current->links as $link) {
                $driver = $this->platforms->forUrl($link);

                if (! $driver || $found->has($driver->platform()->value)) {
                    continue;
                }

                if ($stored = InfluencerAccount::findFromUrl($driver->platform(), $link)) {
                    $found->put($stored->platform->value, $stored->toAccountData()->withSource('link'));
                } elseif ($match = $this->attempt(fn () => $driver->resolve($link))) {
                    $found->put($match->platform->value, $match = $match->withSource('link'));
                    $queue[] = $match;
                }
            }
        }

        if (config('platforms.discovery.same_handle')) {
            foreach ($this->platforms->drivers()->except($found->keys()->all()) as $platform => $driver) {
                if ($match = InfluencerAccount::findByHandle($account->handle, $driver->platform())?->toAccountData()
                    ?? $this->attempt(fn () => $driver->resolve($account->handle))) {
                    $found->put($platform, $match->withSource('handle'));
                }
            }
        }

        return $found->except($account->platform->value);
    }

    protected function attempt(callable $resolve): ?AccountData
    {
        try {
            return $resolve();
        } catch (PlatformException $e) {
            // A failed guess should not break the lookup; the user can still add what was found.
            report($e);

            return null;
        }
    }
}
