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

    'discovery' => [
        // Look for the same username on the other platforms. Each guess on an Apify
        // platform is one actor run, so turn this off to save money.
        'same_handle' => (bool) env('PLATFORM_DISCOVER_SAME_HANDLE', true),
    ],

];
