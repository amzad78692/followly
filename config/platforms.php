<?php

/*
|--------------------------------------------------------------------------
| Platform drivers
|--------------------------------------------------------------------------
|
| Settings for the drivers in App\Platforms. API keys live in
| config/services.php; this file holds the tuning knobs.
|
*/

return [

    // Requests per minute each driver may make before it throws RateLimitedException.
    'rate_limits' => [
        'youtube' => (int) env('YOUTUBE_RATE_LIMIT', 120),
        'instagram' => (int) env('INSTAGRAM_RATE_LIMIT', 10),
        'tiktok' => (int) env('TIKTOK_RATE_LIMIT', 10),
        'x' => (int) env('X_RATE_LIMIT', 10),
    ],

    // Milliseconds to wait between HTTP retries on 429, 5xx and connection errors.
    'retry_backoff' => [500, 2000, 5000],

    // How long a resolved profile is cached, so repeated lookups of the same link cost nothing.
    'resolve_cache_minutes' => (int) env('PLATFORM_RESOLVE_CACHE_MINUTES', 1440),

    // How many recent posts to ask for on each sync.
    'posts_per_fetch' => (int) env('PLATFORM_POSTS_PER_FETCH', 20),

    'sync' => [
        'queue' => env('PLATFORM_SYNC_QUEUE', 'platforms'),

        // Minutes between post syncs per account. Apify platforms cost money per run, so they sync less often.
        'intervals' => [
            'youtube' => (int) env('YOUTUBE_SYNC_MINUTES', 30),
            'instagram' => (int) env('INSTAGRAM_SYNC_MINUTES', 120),
            'tiktok' => (int) env('TIKTOK_SYNC_MINUTES', 120),
            'x' => (int) env('X_SYNC_MINUTES', 120),
        ],

        // Most accounts per platform queued in one scheduler run, biggest accounts first.
        'max_per_run' => [
            'youtube' => (int) env('YOUTUBE_SYNC_MAX', 300),
            'instagram' => (int) env('INSTAGRAM_SYNC_MAX', 100),
            'tiktok' => (int) env('TIKTOK_SYNC_MAX', 100),
            'x' => (int) env('X_SYNC_MAX', 100),
        ],

        // Handles per Apify actor run.
        'apify_batch_size' => (int) env('APIFY_BATCH_SIZE', 20),

        // Hours before a failed account is tried again.
        'failed_retry_hours' => (int) env('PLATFORM_FAILED_RETRY_HOURS', 24),

        'snapshot_retention_days' => (int) env('SNAPSHOT_RETENTION_DAYS', 400),
        'apify_run_retention_days' => (int) env('APIFY_RUN_RETENTION_DAYS', 400),
    ],

    'discovery' => [
        // Look for the same username on the other platforms. Each guess on an Apify
        // platform is one actor run, so turn this off to save money.
        'same_handle' => (bool) env('PLATFORM_DISCOVER_SAME_HANDLE', true),
    ],

];
