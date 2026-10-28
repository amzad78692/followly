<?php

/*
|--------------------------------------------------------------------------
| Remote images
|--------------------------------------------------------------------------
|
| Avatars, covers and post thumbnails come from the platforms' CDNs. They are
| served through the image.remote route (App\Support\RemoteImage), which
| downloads each image once, resizes it with Glide and keeps it on disk.
|
*/

return [

    // Turn off to link straight to the platform CDNs.
    'proxy' => (bool) env('IMAGE_PROXY', true),

    // Seconds to wait for a platform CDN before falling back to its own URL.
    'timeout' => (int) env('IMAGE_PROXY_TIMEOUT', 5),

    // Larger downloads are refused.
    'max_bytes' => 10 * 1024 * 1024,

    // Where downloaded and resized images live.
    'path' => storage_path('app/image-cache'),

    // Images not requested for this many days are removed by images:prune.
    'keep_days' => (int) env('IMAGE_CACHE_DAYS', 30),

    // A failed download is not retried for this many minutes.
    'failure_minutes' => 60,

];
