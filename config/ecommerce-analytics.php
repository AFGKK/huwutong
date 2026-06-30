<?php

// M3-86 电商数据分析报表配置

return [
    'dashboard' => [
        'default_days' => 30,
        'max_days' => 365,
        'cache_ttl_seconds' => 600,
    ],

    'reports' => [
        'sales_trend' => ['granularity' => 'daily', 'comparison' => ['WoW', 'MoM', 'YoY']],
        'repurchase_rate' => ['periods' => [7, 30, 90]],
        'average_order_value' => ['enabled' => true],
        'payment_channel_preference' => ['enabled' => true],
        'top_selling_products' => ['top_n' => 20],
        'sales_forecast' => ['enabled' => true, 'model' => 'moving_average'],
    ],

    'export' => [
        'formats' => ['csv', 'xlsx', 'pdf'],
        'max_rows' => 10000,
        'schedule_enabled' => false,
    ],
];
