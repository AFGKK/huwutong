<?php

// M3-24 设备生命周期画像配置

return [
    'stages' => [
        'new' => [
            'name' => '首次出现',
            'description' => '设备首次出现在系统中',
            'trust_score_range' => [0, 20],
            'min_days' => 0,
        ],
        'onboarding' => [
            'name' => '逐步信任',
            'description' => '设备经过初步验证，信任建立中',
            'trust_score_range' => [21, 60],
            'min_days' => 1,
        ],
        'stable' => [
            'name' => '长期稳定',
            'description' => '设备信任度高，行为稳定可靠',
            'trust_score_range' => [61, 100],
            'min_days' => 7,
        ],
        'suspicious' => [
            'name' => '标记可疑',
            'description' => '设备出现异常行为，需关注',
            'trust_score_range' => [1, 40],
            'min_days' => 0,
        ],
        'retired' => [
            'name' => '废弃',
            'description' => '设备已被废弃或拉黑',
            'trust_score_range' => [0, 0],
            'min_days' => 0,
        ],
    ],

    'trust_score' => [
        'initial' => 30,
        'max' => 100,
        'min' => 0,
        'increase_on_positive' => 5,    // 正面行为加分
        'decrease_on_suspicious' => 20,  // 可疑行为减分
        'decrease_on_blacklist' => 100,  // 拉黑清零
        'daily_decay' => 0,              // 每日衰减
    ],

    'detection' => [
        'suspicious_events' => [
            'geo_jump',              // 地理位置跳跃
            'rapid_activation',      // 快速激活多个License
            'fingerprint_spoof',     // 指纹伪造
            'unusual_hour',          // 非正常时段大量请求
            'multiple_ips',          // 多IP切换
        ],
        'consecutive_suspicious_threshold' => 3,    // 连续可疑事件触发标记
        'auto_retire_after_days' => 180,            // 180天无活动自动废弃
    ],

    'notification' => [
        'on_stage_change' => true,
        'on_suspicious' => true,
        'channels' => ['database', 'mail'],
    ],
];
