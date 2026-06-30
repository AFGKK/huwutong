<?php

// M2-102 邮件营销 Drip 序列配置

return [
    'campaigns' => [
        'max_active_per_tenant' => 10,
        'max_sequences_per_campaign' => 20,
    ],

    'sequences' => [
        'triggers' => [
            'trial_registered' => ['label' => '注册 Trial', 'delay_days' => 0],
            'trial_day_1' => ['label' => 'Trial 第1天', 'delay_days' => 1],
            'trial_day_3' => ['label' => 'Trial 第3天', 'delay_days' => 3],
            'trial_day_7' => ['label' => 'Trial 第7天', 'delay_days' => 7],
            'trial_expiring_3d' => ['label' => 'Trial 过期前3天', 'delay_days' => -3],
            'trial_expired' => ['label' => 'Trial 过期', 'delay_days' => 0],
            'converted' => ['label' => '转化付费', 'delay_days' => 0],
            'inactive_7d' => ['label' => '7天未活跃', 'delay_days' => 7],
            'inactive_30d' => ['label' => '30天未活跃', 'delay_days' => 30],
        ],
        'max_delay_days' => 90,
    ],

    'tracking' => [
        'track_opens' => env('DRIP_TRACK_OPENS', true),
        'track_clicks' => env('DRIP_TRACK_CLICKS', true),
        'retention_days' => 365,
    ],

    'sending' => [
        'per_batch' => env('DRIP_BATCH_SIZE', 100),
        'batch_interval_minutes' => 5,
        'max_per_day_per_recipient' => 3,
        'quiet_hours_start' => '22:00',
        'quiet_hours_end' => '08:00',
    ],
];
