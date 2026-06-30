<?php

// M3-48 客户上传文件云存储配置

return [
    'storage' => [
        'disk' => env('CLOUD_STORAGE_DISK', 's3'), // s3/cos/oss
        'prefix' => 'uploads/{tenant_id}/{type}',
        'max_file_size_kb' => 20480, // 20MB
        'allowed_types' => [
            'logo' => ['image/png', 'image/jpeg', 'image/svg+xml', 'image/webp'],
            'brand_asset' => ['image/png', 'image/jpeg', 'image/svg+xml', 'application/pdf'],
            'document' => ['application/pdf', 'application/msword', 'text/plain'],
            'screenshot' => ['image/png', 'image/jpeg', 'image/webp'],
            'other' => ['*'],
        ],
        'url_expiry_minutes' => 60, // 临时URL过期时间
    ],

    'cdn' => [
        'enabled' => env('CDN_ENABLED', true),
        'domain' => env('CDN_DOMAIN', ''),
        'cache_ttl_seconds' => 86400,
        'purge_on_update' => true,
    ],

    'image_processing' => [
        'resize' => [
            'logo' => ['width' => 400, 'height' => 200],
            'thumbnail' => ['width' => 200, 'height' => 200],
        ],
        'optimize' => true,
        'quality' => 85,
    ],

    'security' => [
        'allowed_origins' => ['*'],
        'require_auth' => true,
        'max_uploads_per_minute' => 10,
        'virus_scan' => env('VIRUS_SCAN_ENABLED', false),
    ],

    'types' => [
        'logo' => ['max_files' => 5, 'public' => true],
        'brand_asset' => ['max_files' => 20, 'public' => true],
        'document' => ['max_files' => 50, 'public' => false],
        'screenshot' => ['max_files' => 100, 'public' => false],
        'other' => ['max_files' => 50, 'public' => false],
    ],
];
