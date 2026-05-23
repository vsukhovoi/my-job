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

    'monobank' => [
        'token'        => env('MONO_TOKEN'),
        'account_iban' => env('MONO_ACCOUNT_IBAN'),
    ],

    'stripe' => [
        'key'            => env('STRIPE_KEY', ''),
        'secret'         => env('STRIPE_SECRET', ''),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', ''),
    ],

    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI', env('APP_URL') . '/auth/google/callback'),
    ],

    'facebook' => [
        'client_id'     => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect'      => env('APP_URL') . '/auth/facebook/callback',
    ],

    'apple' => [
        'client_id'   => env('APPLE_CLIENT_ID'),
        'client_secret' => env('APPLE_CLIENT_SECRET'),
        'redirect'    => env('APP_URL') . '/auth/apple/callback',
    ],

    'telegram_bot' => [
        'api_url'   => env('TELEGRAM_BOT_API_URL', 'http://localhost:8080'),
        'api_token' => env('TELEGRAM_BOT_API_TOKEN'),
    ],

    'telegram' => [
        'callback_secret' => env('TELEGRAM_CALLBACK_SECRET'),
        'webhook_token'   => env('TELEGRAM_WEBHOOK_TOKEN'),
    ],

];
