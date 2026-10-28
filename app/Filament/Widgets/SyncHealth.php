<?php

namespace App\Filament\Widgets;

use App\Enums\SyncStatus;
use App\Filament\Resources\Influencers\InfluencerResource;
use App\Models\InfluencerAccount;
use App\Platforms\Apify\ApifyBudget;
use App\Platforms\PlatformManager;
use App\Platforms\YouTube\YouTubeDriver;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

/**
 * Is syncing healthy: failed accounts, today's YouTube quota and this month's Apify spend.
 */
class SyncHealth extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 2;

    protected ?string $heading = 'Syncing';

    protected function getStats(): array
    {
        return [$this->failedSyncs(), $this->youTubeQuota(), $this->apifySpend()];
    }

    protected function failedSyncs(): Stat
    {
        $failed = InfluencerAccount::where('sync_status', SyncStatus::Failed)->count();
        $syncing = InfluencerAccount::where('sync_status', SyncStatus::Syncing)->count();

        return Stat::make('Failed syncs', Number::format($failed))
            ->description("{$syncing} syncing now")
            ->icon(Heroicon::OutlinedExclamationTriangle)
            ->color($failed ? 'danger' : 'success')
            ->url(InfluencerResource::getUrl('index', ['filters' => ['sync_failed' => ['isActive' => true]]]));
    }

    protected function youTubeQuota(): Stat
    {
        $driver = app(PlatformManager::class)->driver('youtube');
        $used = $driver instanceof YouTubeDriver ? $driver->quotaUsedToday() : 0;
        $limit = (int) config('services.youtube.daily_quota');
        $share = $limit ? $used / $limit : 0;

        return Stat::make('YouTube quota today', Number::format($used).' / '.Number::format($limit))
            ->description(Number::percentage($share * 100).' used, resets at midnight Pacific')
            ->icon(Heroicon::OutlinedPlayCircle)
            ->color($share >= 0.9 ? 'danger' : ($share >= 0.7 ? 'warning' : 'success'));
    }

    protected function apifySpend(): Stat
    {
        $budget = app(ApifyBudget::class);
        $spent = $budget->spent();
        $limit = $budget->limit();

        return Stat::make('Apify spend this month', Number::currency($spent, 'USD'))
            ->description($limit > 0
                ? 'of '.Number::currency($limit, 'USD').' budget'.($budget->reached() ? ', reached: Apify syncs paused' : '')
                : 'No budget set')
            ->icon(Heroicon::OutlinedCurrencyDollar)
            ->color($budget->reached() ? 'danger' : ($limit > 0 && $spent >= $limit * 0.8 ? 'warning' : 'success'));
    }
}
