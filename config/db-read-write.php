<?php

/**
 * 数据库读写分离 & Redis 缓存预热配置 (M2-23)
 *
 * 读写分离：主库写入，从库读取，降低主库负载。
 * 缓存预热：系统启动/低峰期提前加载热点数据到 Redis。
 */
return [

    /*
    |--------------------------------------------------------------------------
    | 读写分离配置
    |--------------------------------------------------------------------------
    */
    'read_write' => [
        'enabled' => env('DB_READ_WRITE_SPLITTING', false),

        // 从库连接配置名称（对应 config/database.php connections）
        'replica_connection' => env('DB_REPLICA_CONNECTION', 'mysql_replica'),

        // 读流量分配到从库的比例 (0~100, 100=全部读走从库)
        'read_percent' => (int) env('DB_READ_PERCENT', 100),

        // 强制走主库的操作类型（写操作自动走主库）
        'force_master_operations' => ['create', 'update', 'delete', 'store', 'destroy'],

        // 健康检查：从库延迟超过此秒数则降级到主库读取
        'replica_max_lag_seconds' => (int) env('DB_REPLICA_MAX_LAG', 5),

        // 健康检查间隔（秒）
        'health_check_interval' => 60,

        // 熔断：连续失败 N 次后暂时禁用从库
        'circuit_breaker_threshold' => 3,
        'circuit_breaker_recovery_seconds' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis 缓存预热配置
    |--------------------------------------------------------------------------
    */
    'cache_warmup' => [
        'enabled' => env('CACHE_WARMUP_ENABLED', true),

        // 预热数据源（表名 → 缓存策略）
        'sources' => [
            'products' => [
                'key_prefix' => 'product:',
                'query' => 'select id, name, type, status, metadata from products where is_active = 1',
                'ttl' => 3600, // 1 小时
                'batch_size' => 100,
            ],
            'feature_flags' => [
                'key_prefix' => 'feature_flag:',
                'query' => 'select id, key, name, enabled, rules from feature_flags where is_active = 1',
                'ttl' => 1800,
                'batch_size' => 200,
            ],
            'plans' => [
                'key_prefix' => 'plan:',
                'query' => 'select id, name, type, price, features from plans where is_active = 1',
                'ttl' => 3600,
                'batch_size' => 50,
            ],
        ],

        // 预热执行时间（cron 表达式）
        'schedule' => env('CACHE_WARMUP_SCHEDULE', '0 3 * * *'), // 每天凌晨 3 点

        // 预热超时（秒）
        'timeout' => 300,
    ],

    /*
    |--------------------------------------------------------------------------
    | 监控配置
    |--------------------------------------------------------------------------
    */
    'monitoring' => [
        // 日志通道
        'log_channel' => env('DB_MONITOR_LOG_CHANNEL', 'stack'),

        // 告警阈值：从库延迟超过此秒数时告警
        'alert_lag_threshold' => 10,
    ],
];
