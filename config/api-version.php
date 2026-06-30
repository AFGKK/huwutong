<?php

// M2-33 API 版本管理配置

return [
    'default_version' => env('API_DEFAULT_VERSION', 'v1'),
    'latest_version' => env('API_LATEST_VERSION', 'v1'),

    'versions' => [
        'v1' => [
            'label' => 'v1 (稳定)',
            'status' => 'active',
            'deprecated_at' => null,
            'sunset_at' => null,
            'migration_guide' => null,
        ],
    ],

    'policies' => [
        'deprecation_notice_days' => env('API_DEPRECATION_NOTICE_DAYS', 180),
        'min_version_lifetime_days' => 90,
        'max_active_versions' => 3,
        'breaking_change_requires_major' => true,
    ],

    'tracking' => [
        'log_api_calls' => env('API_VERSION_TRACKING', true),
        'retention_days' => 365,
        'alert_on_deprecated_usage' => true,
    ],
];
