<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Session Driver
    |--------------------------------------------------------------------------
    */

    'driver' => env('SESSION_DRIVER', 'database'),

    'lifetime' => env('SESSION_LIFETIME', 120),

    'expire_on_close' => env('SESSION_EXPIRE_ON_CLOSE', false),

    'encrypt' => env('SESSION_ENCRYPT', false),

    'connection' => env('SESSION_CONNECTION'),

    'table' => 'sessions',

    'store' => env('SESSION_STORE'),

    'lottery' => [2, 100],

    'cookie' => env('SESSION_COOKIE', Str::slug(env('APP_NAME', 'laravel'), '_') . '_session'),

    /*
    |--------------------------------------------------------------------------
    | Session Security (hardened for government HRIS / DPA compliance)
    |--------------------------------------------------------------------------
    */

    'path' => '/',

    'domain' => env('SESSION_DOMAIN'),

    'secure' => env('SESSION_SECURE_COOKIE', true),

    'http_only' => true,

    'same_site' => 'lax',

    'partitioned' => env('SESSION_PARTITIONED_COOKIE', false),

];
