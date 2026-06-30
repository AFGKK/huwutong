<?php

/**
 * M2-17b SDK 在线验证本地缓存 + 离线宽限期配置
 *
 * SDK 本地缓存 License 验证结果，支持网络抖动时离线验证。
 * 依赖 M2-16 SDK版本兼容策略、M1.3-04 本地加密缓存。
 */
return [

    /*
    |--------------------------------------------------------------------------
    | 缓存基本配置
    |--------------------------------------------------------------------------
    */
    'cache' => [
        // 是否启用本地缓存
        'enabled' => env('SDK_CACHE_ENABLED', true),

        // 缓存默认 TTL（秒），SDK 缓存验证结果的有效期
        'ttl' => env('SDK_CACHE_TTL', 86400), // 24h

        // 缓存最大条目数（防止缓存膨胀）
        'max_entries' => env('SDK_CACHE_MAX_ENTRIES', 1000),

        // 缓存存储方式
        'storage' => env('SDK_CACHE_STORAGE', 'encrypted_file'), // encrypted_file | sqlite | memory

        // 加密算法
        'encryption' => env('SDK_CACHE_ENCRYPTION', 'aes-256-gcm'),

        // 缓存文件路径（相对 SDK 根目录）
        'file_path' => '.huwutong/cache/validation.cache',
    ],

    /*
    |--------------------------------------------------------------------------
    | 离线宽限期配置
    |--------------------------------------------------------------------------
    */
    'grace_period' => [
        // 是否启用离线宽限期
        'enabled' => env('SDK_GRACE_PERIOD_ENABLED', true),

        // 缓存过期后的宽限期天数（缓存失效后仍可离线使用N天）
        'days' => env('SDK_GRACE_PERIOD_DAYS', 7),

        // 宽限期总秒数
        'seconds' => env('SDK_GRACE_PERIOD_DAYS', 7) * 86400,

        // 宽限期结束后是否强制锁死（true=停止服务，false=降级为有限功能）
        'lock_on_expiry' => env('SDK_GRACE_LOCK_ON_EXPIRY', true),

        // 宽限期内的降级模式
        'degraded_mode' => env('SDK_GRACE_DEGRADED_MODE', 'readonly'), // readonly | limited_features | full
    ],

    /*
    |--------------------------------------------------------------------------
    | 网络抖动处理
    |--------------------------------------------------------------------------
    */
    'network' => [
        // 网络超时阈值（ms），超过此值触发缓存读取
        'timeout_threshold' => env('SDK_NETWORK_TIMEOUT_THRESHOLD', 3000),

        // 重试次数
        'retry_count' => env('SDK_NETWORK_RETRY_COUNT', 2),

        // 重试间隔（ms）
        'retry_interval' => env('SDK_NETWORK_RETRY_INTERVAL', 500),

        // 网络不可用时是否静默降级（true=不报错直接用缓存，false=返回网络错误）
        'silent_fallback' => env('SDK_NETWORK_SILENT_FALLBACK', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | 防篡改配置
    |--------------------------------------------------------------------------
    */
    'tamper' => [
        // 是否启用防篡改校验
        'enabled' => env('SDK_CACHE_TAMPER_ENABLED', true),

        // 校验算法
        'algorithm' => env('SDK_CACHE_TAMPER_ALGORITHM', 'hmac-sha256'),

        // 缓存完整性校验密钥（SDK 编译时内置）
        'hmac_key' => env('SDK_CACHE_HMAC_KEY'),

        // 检测到篡改后的行为
        'on_tamper' => env('SDK_CACHE_ON_TAMPER', 'invalidate'), // invalidate | alert | destroy
    ],

    /*
    |--------------------------------------------------------------------------
    | 服务端缓存同步配置
    |--------------------------------------------------------------------------
    */
    'sync' => [
        // 是否启用服务端缓存状态追踪
        'server_tracking' => env('SDK_CACHE_SERVER_TRACKING', true),

        // 缓存状态上报间隔（秒）
        'report_interval' => env('SDK_CACHE_REPORT_INTERVAL', 3600), // 1h

        // 批量上报最大条目数
        'batch_size' => env('SDK_CACHE_BATCH_SIZE', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | 缓存失效推送（对接 M2-134）
    |--------------------------------------------------------------------------
    */
    'invalidation' => [
        'enabled' => env('SDK_CACHE_INVALIDATION_ENABLED', true),
        'on_license_change' => true,   // License状态变更时失效
        'on_device_change' => true,     // 设备变更时失效
        'on_feature_change' => true,    // Feature Flag变更时失效
    ],
];
