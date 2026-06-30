<?php

// M2-137 Temporal 工作流引擎配置

return [
    'engine' => [
        'driver' => env('WORKFLOW_DRIVER', 'temporal'), // temporal|sync
        'temporal' => [
            'host' => env('TEMPORAL_HOST', 'localhost'),
            'port' => env('TEMPORAL_PORT', 7233),
            'namespace' => env('TEMPORAL_NAMESPACE', 'huwutong'),
            'task_queue' => env('TEMPORAL_TASK_QUEUE', 'license-workflows'),
            'tls_enabled' => env('TEMPORAL_TLS', false),
        ],
        'max_retries' => 3,
        'retry_interval_seconds' => 5,
    ],

    'execution' => [
        'timeout_minutes' => 60,
        'max_concurrent' => 10,
        'heartbeat_seconds' => 30,
    ],

    'workflows' => [
        'license_expiry' => [
            'label' => 'License 过期流程',
            'steps' => [
                'notify_license_expiry',
                'enter_grace_period',
                'expire_license',
                'disable_feature_flags',
                'send_expiry_webhook',
            ],
        ],
        'subscription_renewal' => [
            'label' => '订阅续费流程',
            'steps' => [
                'process_renewal_payment',
                'extend_subscription',
                'extend_licenses',
                'create_renewal_invoice',
            ],
        ],
        'commission_settlement' => [
            'label' => '佣金结算流程',
            'steps' => [
                'freeze_commission',
                'release_commission',
                'approve_payout',
            ],
        ],
        'license_restoration' => [
            'label' => 'License 恢复流程',
            'steps' => [
                'restore_license',
                'enable_feature_flags',
            ],
        ],
    ],
];
