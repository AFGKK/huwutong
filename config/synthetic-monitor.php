<?php

// M2-120 合成监控多区域拨测 配置

return [
    /*
    |--------------------------------------------------------------------------
    | 拨测区域
    |--------------------------------------------------------------------------
    */
    'regions' => [
        'ap-asia' => [
            'name' => '亚太区',
            'name_en' => 'Asia Pacific',
            'locations' => ['Tokyo', 'Singapore', 'Sydney', 'Mumbai'],
            'timeout_ms' => 10000,
        ],
        'eu-europe' => [
            'name' => '欧洲区',
            'name_en' => 'Europe',
            'locations' => 'Frankfurt, London, Paris, Stockholm',
            'timeout_ms' => 15000,
        ],
        'us-north-america' => [
            'name' => '北美区',
            'name_en' => 'North America',
            'locations' => 'Virginia, Oregon, Ohio, California',
            'timeout_ms' => 10000,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 拨测默认参数
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'interval_minutes' => 5,
        'timeout_seconds' => 30,
        'expected_status' => 200,
        'retry_count' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | SLA 阈值
    |--------------------------------------------------------------------------
    */
    'sla' => [
        'uptime_target' => env('SYNTHETIC_UPTIME_TARGET', 99.9),
        'latency_warning_ms' => env('SYNTHETIC_LATENCY_WARNING', 500),
        'latency_critical_ms' => env('SYNTHETIC_LATENCY_CRITICAL', 2000),
        'window_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | 状态页同步 (M2-49)
    |--------------------------------------------------------------------------
    */
    'status_page' => [
        'auto_sync' => env('SYNTHETIC_STATUS_PAGE_SYNC', true),
        'sync_interval_minutes' => 15,
    ],

    /*
    |--------------------------------------------------------------------------
    | 清理
    |--------------------------------------------------------------------------
    */
    'prune' => [
        'results_retention_days' => 90,
        'batch_size' => 1000,
    ],
];
