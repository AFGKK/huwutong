<?php

/**
 * Redis 高可用 (HA) 配置
 *
 * 对应任务: M1.3-17
 * 支持模式:
 *   - sentinel: Redis Sentinel 哨兵模式（默认推荐）
 *   - cluster: Redis Cluster 集群模式
 *   - single: 单节点模式（降级/开发环境）
 *
 * 环境变量参考:
 *   REDIS_MODE=sentinel|cluster|single
 *   REDIS_SENTINEL_SERVICE=mymaster
 *   REDIS_SENTINEL_HOST=127.0.0.1,10.0.0.2,10.0.0.3
 *   REDIS_SENTINEL_PORT=26379
 *   REDIS_SENTINEL_PASSWORD=
 *   REDIS_CLUSTER_SEEDS=node1:7000,node2:7001,node3:7002
 */

return [

    /*
    |--------------------------------------------------------------------------
    | 运行模式
    |--------------------------------------------------------------------------
    */
    'mode' => env('REDIS_MODE', 'sentinel'),

    /*
    |--------------------------------------------------------------------------
    | 哨兵节点配置 (Sentinel 模式)
    |--------------------------------------------------------------------------
    */
    'sentinel' => [
        'service' => env('REDIS_SENTINEL_SERVICE', 'mymaster'),
        'hosts' => explode(',', env('REDIS_SENTINEL_HOST', '127.0.0.1')),
        'port' => env('REDIS_SENTINEL_PORT', '26379'),
        'password' => env('REDIS_SENTINEL_PASSWORD', env('REDIS_PASSWORD')),
        'connect_timeout' => env('REDIS_SENTINEL_CONNECT_TIMEOUT', 3),
        'read_timeout' => env('REDIS_SENTINEL_READ_TIMEOUT', 3),
        'retry_interval' => env('REDIS_SENTINEL_RETRY_INTERVAL', 100),
        'retry_limit' => env('REDIS_SENTINEL_RETRY_LIMIT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cluster 集群配置 (Cluster 模式)
    |--------------------------------------------------------------------------
    */
    'cluster' => [
        'seeds' => explode(',', env('REDIS_CLUSTER_SEEDS', '127.0.0.1:7000')),
        'read_from_replicas' => env('REDIS_CLUSTER_READ_FROM_REPLICAS', true),
        'connect_timeout' => env('REDIS_CLUSTER_CONNECT_TIMEOUT', 5),
        'retry_interval' => env('REDIS_CLUSTER_RETRY_INTERVAL', 10),
        'retry_limit' => env('REDIS_CLUSTER_RETRY_LIMIT', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | 连接池配置
    |--------------------------------------------------------------------------
    */
    'pool' => [
        'enabled' => env('REDIS_POOL_ENABLED', true),
        'min_connections' => env('REDIS_POOL_MIN', 5),
        'max_connections' => env('REDIS_POOL_MAX', 50),
        'wait_timeout' => env('REDIS_POOL_WAIT_TIMEOUT', 3),
        'idle_timeout' => env('REDIS_POOL_IDLE_TIMEOUT', 60),
        'max_lifetime' => env('REDIS_POOL_MAX_LIFETIME', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | 监控与告警
    |--------------------------------------------------------------------------
    */
    'monitoring' => [
        'ping_interval' => env('REDIS_PING_INTERVAL', 5),       // 健康检查间隔（秒）
        'latency_warn_threshold' => env('REDIS_LATENCY_WARN', 50),  // 延迟警告阈值（ms）
        'latency_critical_threshold' => env('REDIS_LATENCY_CRITICAL', 200), // 延迟严重阈值（ms）
        'memory_warn_percent' => env('REDIS_MEMORY_WARN', 80),  // 内存使用警告（%）
        'memory_critical_percent' => env('REDIS_MEMORY_CRITICAL', 95), // 内存严重（%）
        'connected_slaves_warn' => env('REDIS_SLAVES_WARN', 1), // 从库数量警告
        'alert_channels' => explode(',', env('REDIS_ALERT_CHANNELS', 'email,notification')),
    ],

    /*
    |--------------------------------------------------------------------------
    | 故障转移
    |--------------------------------------------------------------------------
    */
    'failover' => [
        'auto_failover_enabled' => env('REDIS_AUTO_FAILOVER', true),
        'max_retries' => env('REDIS_FAILOVER_MAX_RETRIES', 3),
        'retry_delay' => env('REDIS_FAILOVER_RETRY_DELAY', 500), // ms
        'circuit_breaker_threshold' => env('REDIS_CIRCUIT_BREAKER', 10), // 连续失败次数触发熔断
        'circuit_breaker_recovery' => env('REDIS_CIRCUIT_BREAKER_RECOVERY', 30), // 熔断恢复时间（秒）
    ],

    /*
    |--------------------------------------------------------------------------
    | 降级策略
    |--------------------------------------------------------------------------
    */
    'degradation' => [
        'cache_fallback' => env('REDIS_CACHE_FALLBACK', 'file'),     // Redis 不可用时回退到 file 缓存
        'session_fallback' => env('REDIS_SESSION_FALLBACK', 'file'), // Session 回退
        'queue_fallback' => env('REDIS_QUEUE_FALLBACK', 'database'), // 队列回退
        'allow_degraded_mode' => env('REDIS_ALLOW_DEGRADED', true),
    ],

];
