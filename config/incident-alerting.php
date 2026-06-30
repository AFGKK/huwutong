<?php

// M2-122 PagerDuty/OpsGenie 告警集成 配置

return [
    /*
    |--------------------------------------------------------------------------
    | PagerDuty 配置
    |--------------------------------------------------------------------------
    */
    'pagerduty' => [
        'enabled' => env('PAGERDUTY_ENABLED', false),
        'api_key' => env('PAGERDUTY_API_KEY', ''),
        'api_endpoint' => env('PAGERDUTY_API_ENDPOINT', 'https://api.pagerduty.com'),
        'routing_key' => env('PAGERDUTY_ROUTING_KEY', ''),
        'from_email' => env('PAGERDUTY_FROM_EMAIL', 'alerts@huwutong.com'),
        'service_id' => env('PAGERDUTY_SERVICE_ID', ''),
        'escalation_policy_id' => env('PAGERDUTY_ESCALATION_POLICY_ID', ''),
        'timeout' => 15,
    ],

    /*
    |--------------------------------------------------------------------------
    | OpsGenie 配置
    |--------------------------------------------------------------------------
    */
    'opsgenie' => [
        'enabled' => env('OPSGENIE_ENABLED', false),
        'api_key' => env('OPSGENIE_API_KEY', ''),
        'api_endpoint' => env('OPSGENIE_API_ENDPOINT', 'https://api.opsgenie.com/v2'),
        'team_id' => env('OPSGENIE_TEAM_ID', ''),
        'schedule_id' => env('OPSGENIE_SCHEDULE_ID', ''),
        'timeout' => 15,
    ],

    /*
    |--------------------------------------------------------------------------
    | 告警级别映射
    |--------------------------------------------------------------------------
    | severity: critical / warning / info
    | PagerDuty: critical / warning / info
    | OpsGenie: P1 / P2 / P3 / P4 / P5
    |--------------------------------------------------------------------------
    */
    'severity_mapping' => [
        'critical' => [
            'pagerduty' => 'critical',
            'opsgenie' => 'P1',
            'description' => '紧急 — 需要立即处理',
        ],
        'high' => [
            'pagerduty' => 'error',
            'opsgenie' => 'P2',
            'description' => '高 — 尽快处理',
        ],
        'warning' => [
            'pagerduty' => 'warning',
            'opsgenie' => 'P3',
            'description' => '警告 — 需关注',
        ],
        'info' => [
            'pagerduty' => 'info',
            'opsgenie' => 'P4',
            'description' => '信息 — 通知',
        ],
        'low' => [
            'pagerduty' => 'info',
            'opsgenie' => 'P5',
            'description' => '低 — 记录',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 自动推送规则
    |--------------------------------------------------------------------------
    | 哪些告警事件自动推送到 PagerDuty/OpsGenie
    |--------------------------------------------------------------------------
    */
    'auto_push' => [
        'critical_alerts' => true,
        'circuit_breaker_open' => true,
        'service_outage' => true,
        'rate_limit_exceeded' => true,
        'payment_failure' => false,
        'license_expiry' => false,
        'custom' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | 事件时间线回写
    |--------------------------------------------------------------------------
    | 告警闭环后，将解决时间线回写到本地
    |--------------------------------------------------------------------------
    */
    'event_sync' => [
        'enabled' => true,
        'interval_minutes' => 15,
        'max_events_per_sync' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | 确认/升级/解决闭环
    |--------------------------------------------------------------------------
    */
    'acknowledgment' => [
        'auto_ack' => true,
        'escalation_minutes' => 15,
        'escalation_levels' => 3,
    ],
];
