<?php

namespace App\Jobs;

use App\Jobs\Concerns\HandlesPlatformErrors;
use App\Models\InfluencerAccount;
use App\Platforms\Exceptions\AccountNotFoundException;
use App\Platforms\PlatformManager;
use App\Platforms\YouTube\YouTubeDriver;
use App\Sync\AccountSyncer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;

/**
 * Refresh up to 50 YouTube channels with one API call (1 quota unit).
 */
class SyncYouTubeProfiles implements ShouldQueue
{
    use Dispatchable, HandlesPlatformErrors, InteractsWithQueue, Queueable;

    /**
     * @param  array<int, int>  $accountIds
     */
    public function __construct(public array $accountIds)
    {
        $this->onQueue(config('platforms.sync.queue'));
    }

    public function handle(PlatformManager $platforms, AccountSyncer $syncer): void
    {
        $accounts = $this->accounts();
        $driver = $platforms->driver('youtube');

        $this->guard($accounts, function () use ($accounts, $driver, $syncer) {
            $profiles = $driver instanceof YouTubeDriver
                ? $driver->fetchProfiles($accounts)
                // A driver without batching: fetch one by one, leaving out channels that are gone.
                : $accounts->mapWithKeys(fn (InfluencerAccount $account) => [
                    $account->external_id => rescue(fn () => $driver->fetchProfile($account), report: false),
                ])->filter();

            foreach ($accounts as $account) {
                ($profile = $profiles->get($account->external_id))
                    ? $syncer->syncProfile($account, $profile)
                    : $account->markSyncFailed(AccountNotFoundException::for($account->platform, $account->external_id));
            }
        });
    }

    protected function accounts(): Collection
    {
        return InfluencerAccount::with('influencer')->whereKey($this->accountIds)->get();
    }
}
