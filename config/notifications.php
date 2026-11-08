<?php

/*
|--------------------------------------------------------------------------
| Notifications
|--------------------------------------------------------------------------
|
| New post notifications (App\Listeners\NotifyFollowersOfNewPosts) and the
| email digest (notifications:digest).
|
*/

return [

    // Posts published longer ago than this don't notify. A creator's first sync stores their
    // recent back catalogue, and nobody wants twenty alerts for last month's videos.
    'max_post_age_hours' => (int) env('NOTIFY_MAX_POST_AGE_HOURS', 48),

    'digest' => [
        // When the digests go out (server time). Weekly digests go out on Mondays.
        'time' => env('DIGEST_TIME', '08:00'),

        // Most notifications listed in one digest email.
        'max_items' => (int) env('DIGEST_MAX_ITEMS', 20),
    ],

    // Local hour (0-23) when the daily summary is pushed to watches and phones.
    'daily_summary_hour' => (int) env('DAILY_SUMMARY_HOUR', 20),

    // Keep read notifications this many days (model:prune). Unread ones are kept.
    'retention_days' => (int) env('NOTIFICATION_RETENTION_DAYS', 90),

];
