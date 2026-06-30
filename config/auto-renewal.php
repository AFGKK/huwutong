<?php

// M3-85 订阅商品自动续费+自助升级/降级配置

return [
    'renewal' => [
        'attempt_interval_hours' => 24,
        'max_retries' => 3,
        'grace_days' => 7,
        'cancel_on_max_retries' => true,
        'notify_days_before' => [7, 3, 1],
    ],

    'upgrade' => [
        'pro_rate_refund' => true,
        'min_days_before_upgrade' => 1,
        'immediate_activation' => true,
    ],

    'downgrade' => [
        'apply_at_period_end' => true,
        'refund_difference' => false,
        'notify_days_before_downgrade' => 3,
    ],

    'billing_periods' => [
        'monthly' => ['months' => 1, 'label' => '每月'],
        'quarterly' => ['months' => 3, 'label' => '每季度'],
        'semi_annually' => ['months' => 6, 'label' => '每半年'],
        'annually' => ['months' => 12, 'label' => '每年'],
    ],
];
