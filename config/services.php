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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'yandex' => [
    'user_agent'      => env('YANDEX_USER_AGENT', 'Mozilla/5.0'),
    'proxy'           => env('YANDEX_PROXY'),
    'timeout'         => (int) env('YANDEX_TIMEOUT', 20),
    'throttle_min_ms' => (int) env('YANDEX_THROTTLE_MIN_MS', 400),
    'throttle_max_ms' => (int) env('YANDEX_THROTTLE_MAX_MS', 1200),
    'max_pages'       => (int) env('YANDEX_MAX_PAGES', 20),
],
];
