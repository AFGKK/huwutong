<?php

// 本地 License 代理模式配置 (M3-12)

return [

    /*
    |--------------------------------------------------------------------------
    | 代理节点默认配置
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'sync_mode' => 'poll',               // poll|push 同步模式
        'sync_interval_seconds' => 300,       // 配置同步间隔(5分钟)
        'heartbeat_interval_seconds' => 60,   // 心跳间隔(1分钟)
        'cache_ttl_seconds' => 86400,         // License缓存TTL(24小时)
        'max_cached_licenses' => 1000,        // 最大缓存License数
        'allow_offline_activation' => true,   // 允许离线激活
        'require_cloud_validation' => false,  // 是否强制云端验证
        'allowed_actions' => ['validate', 'activate', 'deactivate'],
    ],

    /*
    |--------------------------------------------------------------------------
    | 节点管理
    |--------------------------------------------------------------------------
    */
    'node' => [
        'max_per_tenant' => 50,               // 每租户最大节点数
        'health_timeout_minutes' => 10,       // 健康超时(无心跳判定下线)
        'max_pending_hours' => 72,            // 待激活节点自动清理时间
        'api_key_length' => 48,               // API Key 长度
    ],

    /*
    |--------------------------------------------------------------------------
    | 缓存管理
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'max_expired_cache_days' => 30,       // 过期缓存保留天数
        'stale_cache_grace_seconds' => 3600,  // 缓存过期宽限(1小时)
        'background_refresh' => true,         // 后台刷新缓存
    ],

    /*
    |--------------------------------------------------------------------------
    | 安全
    |--------------------------------------------------------------------------
    */
    'security' => [
        'require_tls' => env('LOCAL_PROXY_REQUIRE_TLS', false),
        'ip_whitelist_enabled' => false,
        'max_failed_attempts' => 10,
        'lockout_minutes' => 30,
        'signature_algorithm' => 'hmac-sha256',
    ],

    /*
    |--------------------------------------------------------------------------
    | 清理配置
    |--------------------------------------------------------------------------
    */
    'cleanup' => [
        'heartbeat_retention_days' => 30,     // 心跳日志保留
        'activation_log_retention_days' => 90, // 激活日志保留
        'cleanup_batch_size' => 500,          // 每批清理数
    ],
];
