<?php

/**
 * 蓝绿部署配置 (M3-63)
 */
return [
    'enabled' => env('BLUE_GREEN_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | 默认环境名称
    |--------------------------------------------------------------------------
    */
    'environments' => [
        'active' => 'blue',   // blue | green
        'standby' => 'green', // green | blue
    ],

    /*
    |--------------------------------------------------------------------------
    | 预热阶段
    |--------------------------------------------------------------------------
    */
    'warmup' => [
        'enabled' => true,
        'health_checks' => [
            '/api/health',
            '/api/edge/health',
        ],
        'min_ready_pods' => 2,
        'timeout_seconds' => 300,
        'verify_duration_seconds' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | 流量切换
    |--------------------------------------------------------------------------
    */
    'traffic_switch' => [
        'strategy' => 'instant', // instant | gradual
        'gradual_steps' => [
            ['weight' => 10, 'duration_seconds' => 60],
            ['weight' => 30, 'duration_seconds' => 60],
            ['weight' => 50, 'duration_seconds' => 60],
            ['weight' => 80, 'duration_seconds' => 60],
            ['weight' => 100, 'duration_seconds' => 0],
        ],
        'canary_validation' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | 自动回滚
    |--------------------------------------------------------------------------
    */
    'rollback' => [
        'auto_rollback_on_failure' => true,
        'monitoring_duration_seconds' => 300,
        'error_threshold_percent' => 5,
        'latency_threshold_ms' => 2000,
    ],
];
