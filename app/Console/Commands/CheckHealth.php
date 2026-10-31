<?php

namespace App\Console\Commands;

use App\Enums\Platform;
use App\Enums\SyncStatus;
use App\Models\Admin;
use App\Models\InfluencerAccount;
use App\Notifications\HealthAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

class CheckHealth extends Command
{
    protected $signature = 'monitor:check';

    protected $description = 'Email admins about failed queue jobs and a high sync error rate';

    protected const CHECKED_AT = 'monitor:failed-jobs:checked-at';

    public function handle(): int
    {
        $this->failedJobs();
        $this->syncErrors();

        return self::SUCCESS;
    }

    /**
     * Jobs land in failed_jobs only once their retries are used up, so each one is worth a look.
     */
    protected function failedJobs(): void
    {
        $since = now()->setTimestamp(Cache::get(self::CHECKED_AT, now()->subHour()->getTimestamp()));
        Cache::forever(self::CHECKED_AT, now()->getTimestamp());

        $failed = DB::table('failed_jobs')->where('failed_at', '>', $since)->latest('failed_at')->get(['payload', 'exception']);

        if ($failed->isEmpty()) {
            return;
        }

        $jobs = $failed->countBy(fn ($job) => json_decode($job->payload, true)['displayName'] ?? 'Unknown job');

        $this->notifyAdmins("Followly: {$failed->count()} queued ".Str::plural('job', $failed->count()).' failed', [
            "{$failed->count()} queued ".Str::plural('job', $failed->count())." failed since {$since->toDayDateTimeString()}:",
            ...$jobs->map(fn (int $count, string $job) => "{$job}: {$count}")->values(),
            'Latest error: '.Str::limit(strtok($failed->first()->exception, "\n"), 300),
            'List them with `php artisan queue:failed`, and retry with `php artisan queue:retry all` once the cause is fixed.',
        ]);

        $this->warn("{$failed->count()} failed jobs reported.");
    }

    protected function syncErrors(): void
    {
        $platforms = InfluencerAccount::tracked()->toBase()
            ->selectRaw('platform, count(*) as total, sum(case when sync_status = ? then 1 else 0 end) as failed', [SyncStatus::Failed->value])
            ->groupBy('platform')
            ->get();

        [$total, $failed] = [(int) $platforms->sum('total'), (int) $platforms->sum('failed')];

        if ($failed < config('operations.sync_errors.min_failed') || $failed / $total < config('operations.sync_errors.rate')) {
            return;
        }

        if (! Cache::add('monitor:sync-errors:'.today()->toDateString(), true, now()->endOfDay())) {
            return;
        }

        $error = InfluencerAccount::tracked()->where('sync_status', SyncStatus::Failed)->pluck('sync_error')->filter()->countBy()->sortDesc()->keys()->first();

        $this->notifyAdmins("Followly: {$failed} tracked accounts are failing to sync", [
            "{$failed} of {$total} tracked accounts (".Number::percentage($failed / $total * 100).') failed their last sync:',
            ...$platforms->where('failed', '>', 0)->map(fn ($row) => Platform::from($row->platform)->label().": {$row->failed} of {$row->total}"),
            ...($error ? ['Most common error: '.Str::limit($error, 300)] : []),
            'See Admin → Influencers with the "has a failed sync" filter.',
        ]);

        $this->warn("{$failed} of {$total} tracked accounts are failing.");
    }

    /**
     * @param  iterable<int, string>  $lines
     */
    protected function notifyAdmins(string $subject, iterable $lines): void
    {
        Notification::send(Admin::all(), new HealthAlert($subject, collect($lines)->values()->all()));
    }
}
