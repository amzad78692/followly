<?php

namespace App\Jobs;

use App\Jobs\Concerns\HandlesPlatformErrors;
use App\Models\InfluencerAccount;
use App\Sync\AccountSyncer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Refresh one account's followers and avatar and record today's snapshot.
 */
class SyncAccountProfile implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, HandlesPlatformErrors, InteractsWithQueue, Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public InfluencerAccount $account)
    {
        $this->onQueue(config('platforms.sync.queue'));
    }

    public function uniqueId(): string
    {
        return (string) $this->account->id;
    }

    public function handle(AccountSyncer $syncer): void
    {
        $this->guard($this->account, fn () => $syncer->syncProfile($this->account));
    }

    protected function accounts(): Collection
    {
        return collect([$this->account]);
    }
}
