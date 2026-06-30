<?php

// M2-04 异常检测配置

return [

    /*
    |--------------------------------------------------------------------------
    | 检测开关
    |--------------------------------------------------------------------------
    */
    'enabled' => env('ANOMALY_DETECTION_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | 检测规则
    |--------------------------------------------------------------------------
    */
    'rules' => [
        'ip_batch_activation' => [
            'enabled' => true,
            'label' => 'IP 批量激活',
            'description' => '同一 IP 在短时间内激活多个不同 License',
            // 阈值：N 分钟内超过 M 次
            'threshold_minutes' => 30,
            'threshold_count' => 5,
            'severity' => 'high',
        ],
        'unusual_operation' => [
            'enabled' => true,
            'label' => '非常规操作',
            'description' => '非工作时间批量操作/异常时间段大量操作',
            // 非工作时间: 23:00-06:00
            'quiet_hours_start' => '23:00',
            'quiet_hours_end' => '06:00',
            'threshold_count' => 10,
            'severity' => 'medium',
        ],
        'rapid_geo_switch' => [
            'enabled' => true,
            'label' => '快速地理位置切换',
            'description' => '短时间内从不同国家/地区激活',
            'threshold_minutes' => 60,
            'threshold_count' => 3,
            'severity' => 'critical',
        ],
        'brute_force_attempt' => [
            'enabled' => true,
            'label' => '暴力尝试',
            'description' => '短时间内连续失败激活尝试',
            'threshold_minutes' => 15,
            'threshold_count' => 10,
            'severity' => 'critical',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 自动处置
    |--------------------------------------------------------------------------
    */
    'remediation' => [
        // 自动封禁 IP（critical 级别异常）
        'auto_block_ip' => env('ANOMALY_AUTO_BLOCK_IP', true),
        // IP 封禁时长（分钟）
        'block_duration_minutes' => 120,
        // 自动创建告警
        'auto_alert' => true,
        // 通知管理员角色
        'notify_roles' => ['super-admin', 'admin'],
    ],

    /*
    |--------------------------------------------------------------------------
    | 保留策略
    |--------------------------------------------------------------------------
    */
    'retention' => [
        // 异常记录保留天数
        'days' => env('ANOMALY_RETENTION_DAYS', 90),
    ],

];
