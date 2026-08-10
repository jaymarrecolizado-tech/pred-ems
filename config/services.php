<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third-Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third-party services such
    | as the SMS gateway. This file provides a single place to manage all
    | external service credentials, and the values are read from .env.
    |
    | IMPORTANT: Always read config values via config('services.sms.*') — never
    | call env() directly in service classes, because env() returns null once
    | the config is cached (php artisan config:cache) in production.
    |
    */

    'sms' => [
        'enabled' => env('SMS_ENABLED', false),
        'url' => env('SMS_GATEWAY_URL', 'https://api.sms-gate.app'),
        'username' => env('SMS_GATEWAY_USERNAME', ''),
        'password' => env('SMS_GATEWAY_PASSWORD', ''),
        'path' => env('SMS_API_PATH', '/3rdparty/v1/messages'),
        'country_code' => env('SMS_DEFAULT_COUNTRY_CODE', 63),
        'timeout' => env('SMS_TIMEOUT_SECONDS', 15),
        'max_length' => env('SMS_MAX_MESSAGE_LENGTH', 320),
    ],

];
