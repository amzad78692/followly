<?php

namespace App\Filament\Widgets;

use App\Models\ApifyRun;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Number;

/**
 * Queue backlog and sync timing. Only Apify runs record a duration, so YouTube syncs are not timed.
 */
class Performance extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 8;

    protected ?string $heading = 'Performance';

    /**
     * @return array<string, int> jobs waiting per queue the worker reads
     */
    public function queueSizes(): array
    {
        return collect(['default', config('platforms.sync.queue')])->unique()
            ->mapWithKeys(fn (string $queue) => [$queue => Queue::size($queue)])->all();
    }

    public function averageApifyRunSeconds(): ?float
    {
        $ms = ApifyRun::where('created_at', '>=', now()->subDay())->whereNotNull('duration_ms')->avg('duration_ms');

        return $ms === null ? null : round($ms / 1000, 1);
    }

    protected function getStats(): array
    {
        $sizes = $this->queueSizes();
        $seconds = $this->averageApifyRunSeconds();

        return [
            Stat::make('Jobs waiting', Number::format(array_sum($sizes)))
                ->description(collect($sizes)->map(fn ($n, $queue) => "{$queue}: {$n}")->join(' · ')),
            Stat::make('Average Apify run (24h)', $seconds === null ? 'No runs' : "{$seconds}s")
                ->description('Time Apify takes to scrape a batch'),
        ];
    }
}
