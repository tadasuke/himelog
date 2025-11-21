<?php

return [
    'name' => env('APP_NAME', 'Himelog'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => env('APP_TIMEZONE', 'UTC'),
    'locale' => 'ja',
    'fallback_locale' => 'en',
    'faker_locale' => 'ja_JP',
    'key' => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',
];

