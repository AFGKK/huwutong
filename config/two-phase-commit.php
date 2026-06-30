<?php

// M3-13 两阶段提交配置

return [
    'default_ttl' => env('TWO_PHASE_TTL', 300), // 5分钟

    'reservation' => [
        'max_active_per_license' => 50,
        'max_ttl_seconds' => 3600,
        'cleanup_batch_size' => 100,
        'auto_expire_check_minutes' => 5,
    ],

    'lock' => [
        'timeout_seconds' => 10,
        'wait_seconds' => 5,
        'prefix' => 'hwt:2pc:lock:',
    ],

    'monitoring' => [
        'alert_expired_before_commit' => true,
        'track_failed_commits' => true,
        'retention_days' => 90,
    ],
];
