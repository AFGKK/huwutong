<?php

// M2-96 客户 API Key 管理配置

return [
    'api_key' => [
        'max_keys_per_customer' => env('API_KEY_MAX_PER_CUSTOMER', 5),
        'key_length' => 48,
        'prefix' => 'hwt_',
        'allowed_abilities' => [
            'licenses:read' => '查看 License',
            'licenses:write' => '管理 License',
            'devices:read' => '查看设备',
            'devices:write' => '管理设备',
            'webhooks:read' => '查看 Webhook',
            'webhooks:write' => '管理 Webhook',
        ],
    ],

    'rate_limit' => [
        'default_rpm' => env('API_KEY_DEFAULT_RPM', 60),
        'premium_rpm' => env('API_KEY_PREMIUM_RPM', 300),
    ],

    'audit' => [
        'log_all_calls' => env('API_KEY_AUDIT', true),
        'retention_days' => 90,
    ],
];
