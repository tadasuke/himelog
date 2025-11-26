<?php

return [
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL') . '/api/auth/google/callback'),
    ],
    'x' => [
        'client_id' => env('X_CLIENT_ID'),
        'client_secret' => env('X_CLIENT_SECRET'),
        'redirect' => env('X_REDIRECT_URI', env('APP_URL') . '/api/auth/x/callback'),
    ],
    'aws' => [
        's3' => [
            'bucket' => env('AWS_S3_BUCKET', ''),
            'region' => env('AWS_DEFAULT_REGION', 'ap-northeast-1'),
            'access_key_id' => env('AWS_ACCESS_KEY_ID', ''),
            'secret_access_key' => env('AWS_SECRET_ACCESS_KEY', ''),
        ],
        'cloudfront' => [
            'url' => env('AWS_CLOUDFRONT_URL', null),
        ],
    ],
    'public_review' => [
        'storage_type' => env('PUBLIC_REVIEW_STORAGE_TYPE'),
        's3_prefix' => env('PUBLIC_REVIEW_S3_PREFIX', 'review/'),
        'base_url' => env('PUBLIC_REVIEW_BASE_URL', null),
    ],
];

