<?php

// M2-141 可嵌入式 Widget 配置

return [
    'token' => [
        'default_expires_in' => env('WIDGET_TOKEN_EXPIRES', 3600),
        'max_expires_in' => 86400,
        'min_expires_in' => 300,
        'signing_algorithm' => 'HS256',
    ],

    'permissions' => [
        'licenses:read' => ['label' => '查看 License', 'default' => true],
        'licenses:write' => ['label' => '管理 License', 'default' => false],
        'devices:read' => ['label' => '查看设备', 'default' => true],
        'devices:write' => ['label' => '管理设备', 'default' => false],
    ],

    'embed' => [
        'allowed_origins' => env('WIDGET_ALLOWED_ORIGINS', '*'),
        'postmessage_timeout_ms' => 5000,
        'loading_placeholder' => true,
        'responsive' => true,
        'min_height' => 400,
        'theme' => [
            'primary_color' => '#409EFF',
            'border_radius' => '8px',
        ],
    ],

    'cache_ttl_seconds' => 300,
];
