<?php

namespace App\Sync;

use App\Enums\Platform;
use App\Enums\SyncStatus;
use App\Jobs\SyncAccountPosts;
use App\Jobs\SyncApifyBatch;
use App\Jobs\SyncYouTubeProfiles;
use App\Models\InfluencerAccount;
use App\Platforms\Apify\ApifyBudget;
use App\Platforms\Apify\ApifyDriver;
use App\Platforms\PlatformManager;
use App\Platforms\YouTube\YouTubeDriver;
use Illuminate\Support\Collection;

/**
 * Decides which accounts sync now and queues the jobs. Only tracked accounts sync,
 * biggest first, so the YouTube quota and Apify spend go to the accounts most people see.
 */
class SyncDispatcher
{
    public function __construct(protected PlatformManager $platforms, protected ApifyBudget $budget) {}

    /**
     * Queue post syncs for accounts that are due.
     *
     * @param  array<int, Platform>|null  $only
     * @return array<string, int> accounts queued per platform
     */
    public function posts(?array $only = null): array
    {
        return collect($only ?? Platform::cases())->mapWithKeys(function (Platform $platform) {
            if ($this->usesApify($platform) && $this->budget->reached()) {
                $this->budget->alert();

                return [$platform->value => 0];
            }

            $accounts = InfluencerAccount::tracked()
                ->where('platform', $platform)
                ->dueForSync(config("platforms.sync.intervals.{$platform->value}"))
                ->orderByDesc('followers')
                ->limit(config("platforms.sync.max_per_run.{$platform->value}"))
                ->get();

            return [$platform->value => $this->dispatch($accounts)];
        })->all();
    }

    /**
     * Queue the daily profile refresh. Only YouTube needs it: Apify runs return the profile
     * with every post sync.
     *
     * @return int accounts queued
     */
    public function profiles(): int
    {
        $ids = InfluencerAccount::tracked()->where('platform', Platform::YouTube)->orderByDesc('followers')->pluck('id');

        $ids->chunk(YouTubeDriver::BATCH_SIZE)->each(fn (Collection $chunk) => SyncYouTubeProfiles::dispatch($chunk->values()->all()));

        return $ids->count();
    }

    /**
     * Queue a sync for the given accounts now, e.g. right after a creator is added.
     *
     * @param  iterable<InfluencerAccount>  $accounts
     * @return int accounts queued
     */
    public function dispatch(iterable $accounts): int
    {
        $accounts = collect($accounts);

        if ($accounts->isEmpty()) {
            return 0;
        }

        InfluencerAccount::whereKey($accounts->pluck('id'))->update(['sync_status' => SyncStatus::Syncing, 'updated_at' => now()]);

        $accounts->groupBy(fn (InfluencerAccount $account) => $account->platform->value)->each(function (Collection $group, string $platform) {
            if ($this->usesApify(Platform::from($platform))) {
                $group->chunk(config('platforms.sync.apify_batch_size'))
                    ->each(fn (Collection $batch) => SyncApifyBatch::dispatch(Platform::from($platform), $batch->pluck('id')->values()->all()));
            } else {
                $group->each(fn (InfluencerAccount $account) => SyncAccountPosts::dispatch($account));
            }
        });

        return $accounts->count();
    }

    protected function usesApify(Platform $platform): bool
    {
        return $this->platforms->driver($platform) instanceof ApifyDriver;
    }
}
