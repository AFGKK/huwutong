<?php

// M2-135 离线公钥版本管理 配置

return [
    /*
    |--------------------------------------------------------------------------
    | 密钥策略
    |--------------------------------------------------------------------------
    */
    'key' => [
        'default_algorithm' => env('PUBLIC_KEY_ALGORITHM', 'ED25519'),
        'validity_days' => (int) env('PUBLIC_KEY_VALIDITY_DAYS', 365),
        'compat_window_days' => (int) env('PUBLIC_KEY_COMPAT_WINDOW', 30),
        'rotation_threshold_days' => (int) env('PUBLIC_KEY_ROTATION_THRESHOLD', 30),
        'max_versions' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | CRL (证书吊销列表)
    |--------------------------------------------------------------------------
    */
    'crl' => [
        'cdn_cache_ttl_seconds' => 3600,
        'max_entries' => 100000,
    ],

    /*
    |--------------------------------------------------------------------------
    | CDN 分发
    |--------------------------------------------------------------------------
    */
    'cdn' => [
        'public_key_url' => env('CDN_PUBLIC_KEY_URL', '/offline/public-key'),
        'crl_url' => env('CDN_CRL_URL', '/offline/crl'),
    ],

    /*
    |--------------------------------------------------------------------------
    | 审计
    |--------------------------------------------------------------------------
    */
    'audit' => [
        'log_key_operations' => env('PUBLIC_KEY_AUDIT', true),
        'retention_days' => 365,
    ],
];
