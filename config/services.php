<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'youtube' => [
        'key' => env('YOUTUBE_API_KEY'),
        // Free daily quota; units used are counted per Pacific day (when Google resets it).
        'daily_quota' => (int) env('YOUTUBE_DAILY_QUOTA', 10000),
    ],

    'apify' => [
        'token' => env('APIFY_TOKEN'),
        // Seconds to wait for a run in one request (Apify allows up to 60) and in total.
        'wait_seconds' => (int) env('APIFY_WAIT_SECONDS', 60),
        'timeout_seconds' => (int) env('APIFY_TIMEOUT_SECONDS', 300),
        // Monthly spend in USD; when reached, Apify runs stop until next month and admins get an email. 0 for no limit.
        'monthly_budget_usd' => (float) env('APIFY_MONTHLY_BUDGET_USD', 0),
        // One actor per platform; each returns the profile and its recent posts.
        'actors' => [
            'instagram' => env('APIFY_INSTAGRAM_ACTOR', 'apify/instagram-profile-scraper'),
            'tiktok' => env('APIFY_TIKTOK_ACTOR', 'clockworks/tiktok-scraper'),
            'x' => env('APIFY_X_ACTOR', 'apidojo/tweet-scraper'),
        ],
    ],

    'myfatoorah' => [
        'test_key' => 'SK_KWT_vVZlnnAqu8jRByOWaRPNId4ShzEDNt256dvnjebuyzo52dXjAfRx2ixW5umjWSUx',
        'callback_url' => env('MF_CALLBACK'),
        'live_key' => env('MF_API_KEY'),
        'is_test' => env('MF_IS_TEST', true),
    ],
];
