<?php

// M3-16 云函数授权（Serverless 短时授权/API配额QPS管控）

return [
    'serverless' => [
        'short_lived_token_ttl_seconds' => 3600,
        'max_tokens_per_function' => 100,
        'allowed_runtimes' => ['nodejs', 'python', 'go', 'rust', 'custom'],
        'invocation' => [
            'max_concurrent' => 100,
            'timeout_seconds' => 30,
            'max_payload_size_kb' => 256,
        ],
    ],

    'quota' => [
        'default_qps' => 10,
        'default_monthly_invocations' => 100000,
        'burst_limit' => 20,
        'quota_window_seconds' => 60,
        'overage_action' => 'throttle', // throttle|reject|alert
    ],

    'metering' => [
        'enabled' => true,
        'granularity' => 'minute',
        'retention_days' => 90,
    ],
];
