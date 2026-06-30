<?php

// M3-27 产品使用分析看板配置

return [
    'dashboard' => [
        'default_period_days' => 30,
        'max_period_days' => 365,
        'cache_ttl_seconds' => 600,
    ],

    'product_ranking' => [
        'top_n' => 20,
        'metrics' => [
            'license_count' => 'License数量',
            'activation_count' => '激活数',
            'activation_rate' => '激活率',
            'revenue' => '收入',
            'growth_rate' => '增长率',
        ],
    ],

    'module_usage' => [
        'tracked_modules' => [
            'core' => '核心授权',
            'offline' => '离线模式',
            'device_binding' => '设备绑定',
            'api' => 'API访问',
            'sso' => '单点登录',
            'audit' => '审计日志',
            'white_label' => '白标',
            'metered' => '用量计费',
            'time_restriction' => '时段限制',
        ],
    ],

    'regional_growth' => [
        'levels' => ['country', 'region', 'city'],
        'heatmap_color_scheme' => 'YlOrRd',
        'min_data_points' => 5,
    ],

    'trends' => [
        'granularity' => 'daily', // daily|weekly|monthly
        'comparison_periods' => ['7d', '30d', '90d'],
    ],
];
