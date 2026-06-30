<?php

// M2-101 Trial→付费转化漏斗配置

return [
    'funnel' => [
        'stages' => [
            'trial_registered' => ['label' => '注册 Trial', 'order' => 1],
            'sdk_downloaded' => ['label' => '下载 SDK', 'order' => 2],
            'sdk_activated' => ['label' => '首次激活 SDK', 'order' => 3],
            'first_validation' => ['label' => '首次验证成功', 'order' => 4],
            'feature_used' => ['label' => '使用核心功能', 'order' => 5],
            'converted' => ['label' => '转化为付费', 'order' => 6],
        ],
        'lookback_days' => 90,
    ],

    'tracking' => [
        'auto_track_events' => true,
        'store_raw_events' => true,
        'retention_days' => 365,
    ],

    'alerts' => [
        'drop_off_threshold_percent' => 20,
        'conversion_rate_below_percent' => 5,
        'notify_on_anomaly' => true,
    ],

    'retention' => [
        'auto_send_reminder_days' => [1, 3, 7],
        'reminder_template' => 'trial_expiring',
        'offer_discount_on_day' => 7,
        'discount_percent' => 15,
    ],
];
