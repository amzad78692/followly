<?php

namespace App\Jobs;

use App\Enums\Platform;
use App\Jobs\Concerns\HandlesPlatformErrors;
use App\Models\InfluencerAccount;
use App\Platforms\Apify\ApifyDriver;
use App\Platforms\Exceptions\AccountNotFoundException;
use App\Platforms\Exceptions\PlatformException;
use App\Platforms\PlatformManager;
use App\Sync\AccountSyncer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;

/**
 * Sync many Instagram, TikTok or X accounts with one actor run. The run returns profiles
 * and posts together, so this updates followers, the daily snapshot and posts at once.
 */
class SyncApifyBatch implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, HandlesPlatformErrors, InteractsWithQueue, Queueable;

    public int $timeout;

    /**
     * @param  array<int, int>  $accountIds
     */
    public function __construct(public Platform $platform, public array $accountIds)
    {
        $this->onQueue(config('platforms.sync.queue'));

        // A run may wait the whole Apify timeout, plus the dataset download and storing.
        $this->timeout = config('services.apify.timeout_seconds') + 120;
    }

    public function uniqueId(): string
    {
        return $this->platform->value.':'.md5(implode(',', $this->accountIds));
    }

    public function handle(PlatformManager $platforms, AccountSyncer $syncer): void
    {
        $accounts = $this->accounts();
        $driver = $platforms->driver($this->platform);

        $this->guard($accounts, function () use ($accounts, $driver, $syncer) {
            $results = $driver instanceof ApifyDriver
                ? $driver->fetchMany($accounts)
                : $accounts->mapWithKeys(fn (InfluencerAccount $account) => [$account->id => [
                    'profile' => $driver->fetchProfile($account),
                    'posts' => $driver->fetchRecentPosts($account),
                ]]);

            foreach ($accounts as $account) {
                $result = $results->get($account->id);

                if (! $result || ! $result['profile']) {
                    $account->markSyncFailed(AccountNotFoundException::for($this->platform, $account->handle));

                    continue;
                }

                // One account's bad data should not stop the rest of the batch.
                try {
                    $syncer->syncProfile($account, $result['profile']);
                    $syncer->syncPosts($account, $result['posts']);
                } catch (PlatformException $e) {
                    $account->markSyncFailed($e);
                }
            }
        });
    }

    protected function accounts(): Collection
    {
        return InfluencerAccount::with('influencer')->whereKey($this->accountIds)->get();
    }
}
