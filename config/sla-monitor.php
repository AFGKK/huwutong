<?php

// M3-20 SLA 指标监控 + 自动化拨测配置

return [
    'probes' => [
        'default_timeout_seconds' => 10,
        'default_interval_minutes' => 5,
        'max_probes_per_tenant' => 50,
        'concurrent_probes' => 10,
        'uptime_calculation_days' => 30,
        'alert_on_consecutive_failures' => 3,
    ],

    'sla_targets' => [
        'uptime' => 99.9,        // SLA 目标可用性 %
        'response_time_p95_ms' => 500,  // P95 响应时间目标
        'response_time_p99_ms' => 2000, // P99 响应时间目标
    ],

    'metrics' => [
        'retention_days' => 90,
        'aggregation_granularity_minutes' => 5,
        'include_path_patterns' => ['/api/*', '/admin/*'],
    ],

    'alerting' => [
        'channels' => ['database', 'mail', 'webhook'],
        'escalation_delay_minutes' => 15,
        'reminder_interval_minutes' => 60,
    ],
];
