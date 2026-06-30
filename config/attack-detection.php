<?php

// M3-36 AI 攻击模式识别配置

return [
    'detectors' => [
        'brute_force' => [
            'enabled' => true,
            'window_minutes' => 5,
            'threshold' => 20,
            'description' => '暴力破解检测：短时间内大量失败请求',
        ],
        'zero_day' => [
            'enabled' => true,
            'threshold_score' => 0.7,
            'description' => '零日利用检测：异常API调用模式/非常见参数',
        ],
        'apt_slow' => [
            'enabled' => true,
            'window_hours' => 72,
            'min_events' => 5,
            'description' => 'APT慢速攻击：长时间跨度低频率试探',
        ],
        'credential_stuffing' => [
            'enabled' => true,
            'window_minutes' => 10,
            'threshold' => 10,
            'description' => '分布式撞库：多IP低频率尝试',
        ],
        'side_channel' => [
            'enabled' => false,
            'description' => '侧信道攻击：响应时间/错误消息分析',
        ],
        'api_abuse' => [
            'enabled' => true,
            'window_minutes' => 60,
            'threshold' => 100,
            'description' => 'API滥用：高频调用/爬虫/数据抓取',
        ],
    ],

    'ai' => [
        'enabled' => env('ATTACK_DETECTION_AI_ENABLED', false),
        'model' => env('ATTACK_DETECTION_AI_MODEL', 'gpt-4'),
        'anomaly_threshold' => 0.8,
        'batch_size' => 50,
        'analysis_interval_minutes' => 5,
    ],

    'response' => [
        'auto_block_ip' => true,
        'auto_suspend_license' => false,
        'auto_alert_admin' => true,
        'auto_create_ticket' => false,
        'block_duration_minutes' => 60,
        'severity_levels' => ['info', 'warning', 'critical'],
    ],

    'logging' => [
        'retention_days' => 90,
        'log_all_events' => false,
        'log_only_above' => 'warning',
    ],
];
