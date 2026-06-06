<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CDN 分发配置
    |--------------------------------------------------------------------------
    */
    'cdn' => [
        // 存储驱动 (local/s3)
        'disk' => env('LICENSE_CDN_DISK', 'local'),

        // CDN 域名（用于生成 CDN 加速 URL）
        'domain' => env('LICENSE_CDN_DOMAIN', env('APP_URL')),

        // CDN 路径前缀
        'prefix' => env('LICENSE_CDN_PREFIX', 'storage'),
    ],
];
