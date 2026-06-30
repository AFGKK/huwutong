<?php

// AI 风控授权 & 行为风控引擎 配置 (M3-01, M3-02)

return [

    /*
    |--------------------------------------------------------------------------
    | AI 风控授权 (M3-01)
    |--------------------------------------------------------------------------
    */
    'fraud_detection' => [
        // 是否启用
        'enabled' => env('FRAUD_DETECTION_ENABLED', true),

        // 风险阈值
        'thresholds' => [
            'critical' => 80,
            'high' => 50,
            'medium' => 25,
        ],

        // 地理位置检测
        'geo_velocity' => [
            'enabled' => true,
            'window_hours' => 24,
            'max_activations_per_window' => 5,
            'high_risk_diff_country_hours' => 1,
            'medium_risk_diff_country_hours' => 6,
        ],

        // 设备指纹检测
        'device_fingerprint' => [
            'enabled' => true,
            'max_devices_per_license' => null, // null = 使用License配置
            'check_virtual_environment' => true,
        ],

        // 激活频率检测
        'activation_frequency' => [
            'enabled' => true,
            'window_minutes' => 60,
            'critical_threshold' => 20,
            'high_threshold' => 10,
            'medium_threshold' => 5,
        ],

        // IP 信誉检测
        'ip_reputation' => [
            'enabled' => true,
            'proxy_ips_cache_ttl' => 3600,
            'max_daily_activations_per_ip' => 50,
        ],

        // 第三方API（可选）
        'third_party' => [
            'ipinfo_token' => env('IPINFO_TOKEN', ''),
            'abuseipdb_key' => env('ABUSEIPDB_API_KEY', ''),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 行为风控引擎 (M3-02)
    |--------------------------------------------------------------------------
    */
    'behavior_engine' => [
        // 是否启用
        'enabled' => env('BEHAVIOR_ENGINE_ENABLED', true),

        // 速率限制
        'rate_limiting' => [
            'enabled' => true,
            'window_seconds' => 60,
            'extreme_threshold' => 100,
            'high_threshold' => 50,
            'medium_threshold' => 20,
        ],

        // 并发检测
        'concurrency' => [
            'enabled' => true,
            'window_seconds' => 10,
            'extreme_threshold' => 50,
            'high_threshold' => 20,
        ],

        // 暴力破解检测
        'brute_force' => [
            'enabled' => true,
            'max_invalid_attempts' => 10,
            'window_seconds' => 3600,
        ],

        // 设备滥用检测
        'device_abuse' => [
            'enabled' => true,
            'max_licenses_per_device' => 10,
        ],

        // 自动封禁
        'auto_ban' => [
            'enabled' => true,
            'ip_ban_duration_seconds' => 3600,       // IP临时封禁1小时
            'device_ban_duration_seconds' => 86400,   // 设备封禁24小时
            'escalation_threshold' => 3,               // 三次封禁后升级为永久
        ],
    ],
];
