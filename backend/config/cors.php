<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['http://localhost:5173', 'http://localhost:3000', 'http://127.0.0.1:5173', 'http://localhost:8000'],
    'allowed_origins_patterns' => [
        '#^https?://.*\.madfaction\.net$#',  // 開発環境用
        '#^https?://.*\.hime-log\.jp$#',     // 本番環境用（追加）
    ],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
    'allowed_origin_patterns' => [],
];

