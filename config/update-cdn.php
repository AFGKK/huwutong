<?php

// M2-69 自动更新包云分发 + CDN 加速配置

return [
    'cdn' => [
        'enabled' => env('UPDATE_CDN_ENABLED', true),
        'provider' => env('UPDATE_CDN_PROVIDER', 'cloudflare'), // cloudflare | aws | aliyun | qiniu | custom
        'base_url' => env('UPDATE_CDN_BASE_URL', 'https://cdn.huwutong.com'),
        'path_prefix' => env('UPDATE_CDN_PATH_PREFIX', 'updates'),
        'cache_ttl' => env('UPDATE_CDN_CACHE_TTL', 86400), // CDN 缓存 1 天
        'signed_url_ttl' => env('UPDATE_CDN_SIGNED_URL_TTL', 3600), // 签名 URL 有效期 1 小时
    ],

    'distribution' => [
        'chunk_size' => env('UPDATE_CHUNK_SIZE', 1048576), // 1MB 分块
        'max_file_size' => env('UPDATE_MAX_FILE_SIZE', 1073741824), // 最大 1GB
        'resume_enabled' => env('UPDATE_RESUME_ENABLED', true), // 断点续传
        'concurrent_downloads' => env('UPDATE_CONCURRENT_DOWNLOADS', 10), // 每 IP 并发下载数
        'rate_limit' => env('UPDATE_RATE_LIMIT', 5), // 每 IP 每分钟下载次数
    ],

    'purge' => [
        'enabled' => env('UPDATE_CDN_PURGE_ENABLED', true),
        'on_publish' => env('UPDATE_CDN_PURGE_ON_PUBLISH', true), // 发布时自动清除缓存
        'on_deprecate' => env('UPDATE_CDN_PURGE_ON_DEPRECATE', true), // 废弃时清除缓存
        'api_token' => env('UPDATE_CDN_PURGE_TOKEN', ''),
        'zone_id' => env('UPDATE_CDN_ZONE_ID', ''),
    ],

    'regions' => [
        'default' => env('UPDATE_CDN_DEFAULT_REGION', 'auto'),
        'available' => ['auto', 'na', 'eu', 'ap', 'sa', 'af', 'oc'],
    ],

    'monitoring' => [
        'track_downloads' => true,
        'track_bandwidth' => true,
        'bandwidth_warning_threshold_mb' => env('UPDATE_BANDWIDTH_WARNING_GB', 100) * 1000,
        'bandwidth_critical_threshold_mb' => env('UPDATE_BANDWIDTH_CRITICAL_GB', 500) * 1000,
    ],
];
