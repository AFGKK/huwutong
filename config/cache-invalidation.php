<?php

// M2-134 SDK 缓存失效主动推送服务 配置

return [
    /*
    |--------------------------------------------------------------------------
    | 推送通道
    |--------------------------------------------------------------------------
    */
    'channels' => [
        'primary' => env('CACHE_INVAL_PRIMARY_CHANNEL', 'reverb'),  // reverb/webhook
        'reverb' => [
            'enabled' => env('CACHE_INVAL_REVERB_ENABLED', true),
            'channel_prefix' => 'sdk-cache.tenant.',
        ],
        'webhook' => [
            'enabled' => env('CACHE_INVAL_WEBHOOK_ENABLED', true),
            'timeout_seconds' => 10,
            'max_retries' => 3,
            'hmac_header' => 'X-Signature-256',
        ],
        'sse' => [
            'enabled' => env('CACHE_INVAL_SSE_ENABLED', true),
            'max_poll_seconds' => 300,     // SSE 最大连接时长
            'poll_interval_seconds' => 30,  // SSE 轮询间隔
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 合并策略
    |--------------------------------------------------------------------------
    */
    'merge' => [
        'enabled' => true,
        'batch_limit' => 50,
        'merge_window_seconds' => 5,  // 5秒内同类型合并
    ],

    /*
    |--------------------------------------------------------------------------
    | 清理
    |--------------------------------------------------------------------------
    */
    'prune' => [
        'retention_days' => 7,
        'batch_size' => 1000,
    ],

    /*
    |--------------------------------------------------------------------------
    | 推送类型
    |--------------------------------------------------------------------------
    */
    'types' => [
        'license_status' => 'License 状态变更',
        'feature_flag' => 'Feature Flag 变更',
        'product_config' => '产品配置变更',
        'heartbeat' => '心跳检查',
    ],
];
