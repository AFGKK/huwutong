<?php

/**
 * PWA 配置 (M3-51)
 *
 * 渐进式 Web 应用 — 离线缓存 + 推送通知 + 添加到主屏幕
 */
return [

    /*
    |--------------------------------------------------------------------------
    | PWA 启用
    |--------------------------------------------------------------------------
    */
    'enabled' => env('PWA_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Service Worker 配置
    |--------------------------------------------------------------------------
    */
    'serviceworker' => [
        'filename' => 'sw.js',
        'path' => '/sw.js',
        'scope' => '/build/',
        'cache_version' => 'v1',
        'precache_urls' => [
            '/build/',
            '/build/login',
            '/build/dashboard',
            '/build/assets/app.css',
            '/manifest.json',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Manifest 配置
    |--------------------------------------------------------------------------
    */
    'manifest' => [
        'name' => env('PWA_NAME', 'HWT License 管理后台'),
        'short_name' => env('PWA_SHORT_NAME', 'HWT License'),
        'description' => '互物通 HWT License 授权管理系统',
        'background_color' => '#1d1e1f',
        'theme_color' => '#409eff',
        'display' => 'standalone',
        'orientation' => 'portrait-primary',
        'start_url' => '/build/login',
        'scope' => '/build/',
    ],

    /*
    |--------------------------------------------------------------------------
    | 推送通知
    |--------------------------------------------------------------------------
    */
    'push_notifications' => [
        'enabled' => env('PWA_PUSH_ENABLED', false),
        'vapid_public_key' => env('PWA_VAPID_PUBLIC_KEY'),
        'vapid_private_key' => env('PWA_VAPID_PRIVATE_KEY'),
        'vapid_subject' => env('PWA_VAPID_SUBJECT', 'mailto:admin@huwutong.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | 缓存策略
    |--------------------------------------------------------------------------
    */
    'caching' => [
        'strategy' => env('PWA_CACHE_STRATEGY', 'staleWhileRevalidate'),
        'max_age_seconds' => env('PWA_CACHE_MAX_AGE', 86400),
        'api_cache_ttl' => env('PWA_API_CACHE_TTL', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | 离线模式
    |--------------------------------------------------------------------------
    */
    'offline' => [
        'enabled' => true,
        'fallback_page' => '/build/offline',
        'cache_network_first_paths' => [
            '/api/*',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 更新策略
    |--------------------------------------------------------------------------
    */
    'update' => [
        'strategy' => 'prompt', // prompt | immediate | background
        'prompt_message' => '发现新版本，是否更新？',
    ],
];
