<?php

// M2-49 公开状态页配置

return [
    'branding' => [
        'site_name' => env('STATUS_PAGE_NAME', 'HWT License'),
        'logo_url' => env('STATUS_PAGE_LOGO', ''),
        'website_url' => env('STATUS_PAGE_WEBSITE', 'https://huwutong.com'),
        'support_url' => env('STATUS_PAGE_SUPPORT', 'https://huwutong.com/support'),
        'custom_css' => env('STATUS_PAGE_CSS', ''),
    ],

    'uptime' => [
        'check_interval_minutes' => env('STATUS_CHECK_INTERVAL', 5),
        'retention_days' => env('STATUS_RETENTION_DAYS', 90),
        'degraded_threshold_ms' => env('STATUS_DEGRADED_MS', 500),
        'down_threshold_ms' => env('STATUS_DOWN_MS', 2000),
        'grace_period_minutes' => 2,
    ],

    'incident' => [
        'auto_close_hours' => 72,
        'max_title_length' => 200,
        'allow_historical_backfill' => true,
    ],

    'subscription' => [
        'enabled' => true,
        'verify_email' => env('STATUS_VERIFY_EMAIL', true),
        'max_subscribers' => 10000,
        'rate_limit_per_ip_hour' => 5,
    ],

    'components' => [
        'defaults' => [
            ['name' => 'API 服务', 'description' => 'REST API 可用性'],
            ['name' => 'License 验证', 'description' => 'License 激活与验证'],
            ['name' => 'Webhook 推送', 'description' => 'Webhook 事件推送'],
            ['name' => '管理后台', 'description' => '管理后台 Web 界面'],
            ['name' => '客户门户', 'description' => '客户自助门户'],
        ],
    ],
];
