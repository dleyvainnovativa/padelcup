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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    'apple' => [
        'client_id' => env('APPLE_CLIENT_ID'),
        'client_secret' => env('APPLE_CLIENT_SECRET'), // JWT, regenerate every 6 months
        'redirect' => env('APPLE_REDIRECT_URI', '/auth/apple/callback'),
    ],

    // Umami Cloud analytics (umami.is). Read-only report of a tournament's
    // public page. 'register_event' is the Umami custom event name you fire on
    // sign-up (leave default if you don't track one). 'path_param' is the query
    // key Umami uses to filter by page path — 'url' on most versions; flip to
    // 'path' if your Cloud account rejects the filter (see the API changelog).
    'umami' => [
        'base' => env('UMAMI_API_URL', 'https://api.umami.is/v1'),
        'api_key' => env('UMAMI_API_KEY'),
        'website_id' => env('UMAMI_WEBSITE_ID'),
        'register_event' => env('UMAMI_REGISTER_EVENT', 'registro'),
        // Page-path filter key(s), comma-separated. Default sends both because
        // Cloud uses 'path' and self-hosted uses 'url'; both take a path value.
        'path_param' => env('UMAMI_PATH_PARAM', 'url,path'),
    ],

];
