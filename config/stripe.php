<?php

return [
    'secret' => env('STRIPE_SECRET'),
    'key' => env('STRIPE_KEY'), // publishable
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    'connect_webhook_secret' => env('STRIPE_CONNECT_WEBHOOK_SECRET'),

    'currency' => 'mxn',

    // Where Stripe sends the manager after onboarding
    'connect' => [
        'refresh_url' => env('APP_URL') . '/connect/refresh',
        'return_url' => env('APP_URL') . '/connect/return',
    ],
];
