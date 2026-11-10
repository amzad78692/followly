<?php

namespace App\Filament\Widgets;

use AchyutN\FilamentLogViewer\LogTable;
use App\Enums\SyncStatus;
use App\Models\InfluencerAccount;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Recent failed queue jobs and the two checks `monitor:check` (CheckHealth) emails about.
 */
class ErrorsAndAlerts extends Widget
{
    use HasWidgetShield;

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.errors-and-alerts';

    /**
     * @return list<array{job: string, error: string, failed_at: Carbon}>
     */
    public function failedJobs(): array
    {
        return DB::table('failed_jobs')->latest('failed_at')->limit(5)->get(['payload', 'exception', 'failed_at'])
            ->map(fn ($job) => [
                'job' => class_basename(json_decode($job->payload, true)['displayName'] ?? 'Unknown job'),
                'error' => Str::limit(strtok($job->exception, "\n"), 160),
                'failed_at' => Carbon::parse($job->failed_at),
            ])->all();
    }

    /**
     * Same thresholds as CheckHealth, so "alerting" here means admins got (or will get) an email.
     *
     * @return list<array{label: string, value: string, alerting: bool}>
     */
    public function checks(): array
    {
        $failedJobs = DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();

        $accounts = InfluencerAccount::tracked()->toBase()
            ->selectRaw('count(*) as total, sum(case when sync_status = ? then 1 else 0 end) as failed', [SyncStatus::Failed->value])
            ->first();
        [$total, $failed] = [(int) $accounts->total, (int) $accounts->failed];

        return [
            ['label' => 'Failed queue jobs (24h)', 'value' => (string) $failedJobs, 'alerting' => $failedJobs > 0],
            [
                'label' => 'Tracked accounts failing to sync',
                'value' => "{$failed} of {$total}",
                'alerting' => $failed >= config('operations.sync_errors.min_failed') && $total && $failed / $total >= config('operations.sync_errors.rate'),
            ],
        ];
    }

    public function logViewerUrl(): string
    {
        return LogTable::getUrl();
    }
}
