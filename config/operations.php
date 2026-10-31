<?php

return [

    /*
    | monitor:check (hourly) emails admins about queued jobs that failed since the last check,
    | and, at most once a day, when this share of tracked accounts failed their last sync
    | (and at least min_failed did, so one broken account among three does not alert).
    */
    'sync_errors' => [
        'rate' => (float) env('MONITOR_SYNC_ERROR_RATE', 0.25),
        'min_failed' => (int) env('MONITOR_SYNC_MIN_FAILED', 3),
    ],

    /*
    | db:backup (daily) writes a gzipped mysqldump to this filesystem disk and deletes backups
    | older than keep_days. The default "local" disk is on the same server: point BACKUP_DISK
    | at an off-site disk (e.g. s3) or copy storage/app/private/backups elsewhere.
    */
    'backup' => [
        'disk' => env('BACKUP_DISK', 'local'),
        'path' => 'backups',
        'keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),
        'mysqldump' => env('BACKUP_MYSQLDUMP_PATH', 'mysqldump'),
    ],

];
