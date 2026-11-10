<?php

namespace App\Filament\Widgets;

use App\Enums\Platform;
use App\Enums\SyncStatus;
use App\Models\InfluencerAccount;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/**
 * Per platform: the last successful sync, syncs that failed in the last 24 hours, and accounts whose last sync failed.
 */
class PlatformStatus extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 5;

    protected ?string $heading = 'Platform status';

    /**
     * @return array<string, array{last_synced_at: ?Carbon, failed_24h: int, errors: int}>
     */
    public function status(): array
    {
        // shortcut: a failed account's updated_at stands in for when its sync failed (no per-attempt log); add a sync log table if exact counts matter.
        $rows = InfluencerAccount::toBase()
            ->selectRaw('platform, max(last_synced_at) as last_synced_at')
            ->selectRaw('sum(case when sync_status = ? and updated_at >= ? then 1 else 0 end) as failed_24h', [SyncStatus::Failed->value, now()->subDay()])
            ->selectRaw('sum(case when sync_status = ? then 1 else 0 end) as errors', [SyncStatus::Failed->value])
            ->groupBy('platform')
            ->get()
            ->keyBy('platform');

        return collect(Platform::cases())->mapWithKeys(fn (Platform $platform) => [$platform->value => [
            'last_synced_at' => ($last = $rows[$platform->value]->last_synced_at ?? null) ? Carbon::parse($last) : null,
            'failed_24h' => (int) ($rows[$platform->value]->failed_24h ?? 0),
            'errors' => (int) ($rows[$platform->value]->errors ?? 0),
        ]])->all();
    }

    protected function getStats(): array
    {
        return collect($this->status())->map(fn (array $s, string $platform) => Stat::make(Platform::from($platform)->label(), $s['last_synced_at']?->diffForHumans() ?? 'Never synced')
            ->description("Last successful sync · {$s['failed_24h']} failed in 24h · {$s['errors']} with errors")
            ->color($s['errors'] ? 'danger' : 'success'))
            ->values()
            ->all();
    }
}
