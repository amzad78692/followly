<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('queue:work --stop-when-empty')
    ->everyMinute()
    ->withoutOverlapping();

// Automatic log cleanup - runs daily at 2:00 AM
Schedule::command('log:cleanup')
    ->when(function () {
        $maxFileSize = config()->integer('filament-log-viewer.max_log_file_size', 2048) * 1024;
        $file_size = filesize(storage_path('logs/laravel.log')) ?? 0;

        return config('filament-log-viewer.auto_cleanup_enabled') && $file_size > $maxFileSize;
    })
    ->onSuccess(fn () => logger()->info('Log cleanup completed successfully'))
    ->onFailure(fn () => logger()->error('Log cleanup failed'));
