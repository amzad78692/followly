<?php

use App\Models\AccountSnapshot;
use App\Models\ApifyRun;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('queue:work --queue=default,'.config('platforms.sync.queue').' --stop-when-empty')
    ->everyMinute()
    ->withoutOverlapping();

// Platform syncing: each run queues only the accounts that are due (see config/platforms.php).
Schedule::command('platforms:sync posts')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

Schedule::command('platforms:sync profiles')
    ->dailyAt('03:00');

// Trending scores and cached follower counts for Explore (see config/discovery.php).
Schedule::command('influencers:rank')
    ->hourly()
    ->withoutOverlapping();

// Email digests of new post notifications (users choose daily or weekly).
Schedule::command('notifications:digest daily')
    ->dailyAt(config('notifications.digest.time'));

Schedule::command('notifications:digest weekly')
    ->weeklyOn(1, config('notifications.digest.time'));

Schedule::call(fn () => DB::table('notifications')
    ->where('read_at', '<', now()->subDays(config('notifications.retention_days')))
    ->delete())
    ->name('prune-read-notifications')
    ->dailyAt('03:45');

Schedule::command('model:prune', ['--model' => [AccountSnapshot::class, ApifyRun::class]])
    ->dailyAt('03:30');

// Automatic log cleanup - runs daily at 2:00 AM
Schedule::command('log:cleanup')
    ->when(function () {
        $maxFileSize = config()->integer('filament-log-viewer.max_log_file_size', 2048) * 1024;
        $file_size = filesize(storage_path('logs/laravel.log')) ?? 0;

        return config('filament-log-viewer.auto_cleanup_enabled') && $file_size > $maxFileSize;
    })
    ->onSuccess(fn () => logger()->info('Log cleanup completed successfully'))
    ->onFailure(fn () => logger()->error('Log cleanup failed'));
