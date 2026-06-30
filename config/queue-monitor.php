<?php

// M2-82 队列死信监控面板配置

return [
    'failed_jobs' => [
        'retention_days' => env('QUEUE_RETENTION_DAYS', 30),
        'max_retry_count' => 3,
        'batch_retry_limit' => 50,
        'auto_cleanup_enabled' => true,
        'alert_threshold' => env('QUEUE_ALERT_THRESHOLD', 10),
    ],

    'monitoring' => [
        'refresh_interval_seconds' => 30,
        'history_days' => 7,
        'chart_points' => 120,
    ],

    'queues' => [
        'default' => ['label' => '默认队列', 'connection' => 'redis'],
        'notifications' => ['label' => '通知队列', 'connection' => 'redis'],
        'webhooks' => ['label' => 'Webhook 队列', 'connection' => 'redis'],
        'emails' => ['label' => '邮件队列', 'connection' => 'redis'],
        'batch' => ['label' => '批量任务', 'connection' => 'redis'],
    ],
];
