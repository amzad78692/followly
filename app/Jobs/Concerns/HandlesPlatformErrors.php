<?php

namespace App\Jobs\Concerns;

use App\Enums\SyncStatus;
use App\Models\InfluencerAccount;
use App\Platforms\Exceptions\AccountNotFoundException;
use App\Platforms\Exceptions\BudgetExceededException;
use App\Platforms\Exceptions\PlatformException;
use App\Platforms\Exceptions\RateLimitedException;
use Closure;
use DateTime;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Shared error handling for the sync jobs:
 * - rate limits release the job until the limit resets (not counted as a failure)
 * - a used-up Apify budget puts the accounts back to pending and stops
 * - a missing account is marked failed and not retried
 * - other platform errors are saved to sync_error and retried with backoff
 */
trait HandlesPlatformErrors
{
    /** Platform errors allowed before the job gives up; rate-limit releases do not count. */
    public int $maxExceptions = 3;

    public function retryUntil(): DateTime
    {
        return now()->addHours(6);
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    /**
     * @param  InfluencerAccount|Collection<int, InfluencerAccount>  $accounts
     */
    protected function guard(InfluencerAccount|Collection $accounts, Closure $sync): void
    {
        $accounts = $accounts instanceof Collection ? $accounts : collect([$accounts]);

        try {
            $sync();
        } catch (BudgetExceededException) {
            $accounts->each(fn (InfluencerAccount $account) => $account->forceFill(['sync_status' => SyncStatus::Pending])->save());
        } catch (RateLimitedException $e) {
            $this->release($e->retryAfter);
        } catch (AccountNotFoundException $e) {
            $accounts->each->markSyncFailed($e);
        } catch (PlatformException $e) {
            $accounts->each->markSyncFailed($e);

            throw $e;
        }
    }

    public function failed(?Throwable $e): void
    {
        $this->accounts()->each(fn (InfluencerAccount $account) => $account->markSyncFailed($e ?? new \RuntimeException('Sync failed')));
    }

    /**
     * @return Collection<int, InfluencerAccount>
     */
    abstract protected function accounts(): Collection;
}
