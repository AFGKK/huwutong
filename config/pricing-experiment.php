<?php

// M3-26 价格实验/A/B定价系统配置

return [
    'experiment' => [
        'types' => [
            'price_point' => '价格点测试（不同定价）',
            'discount' => '折扣策略测试',
            'tier_structure' => '套餐结构测试',
            'billing_period' => '计费周期测试',
            'currency' => '货币定价测试',
            'bundle' => '捆绑销售测试',
        ],

        'default_traffic_split' => 50,
        'minimum_sample_size' => 100,
        'default_confidence_level' => 95,
        'max_concurrent_experiments' => 5,
        'max_duration_days' => 90,
        'min_duration_days' => 1,
    ],

    'target_metrics' => [
        'conversion_rate' => '转化率',
        'revenue_per_user' => '每用户收入',
        'subscription_rate' => '订阅率',
        'churn_rate' => '流失率',
        'upgrade_rate' => '升级率',
        'average_order_value' => '平均订单价值',
        'customer_lifetime_value' => '客户生命周期价值',
    ],

    'segmentation' => [
        'dimensions' => [
            'region' => '地理区域',
            'channel' => '渠道来源',
            'customer_tier' => '客户等级',
            'industry' => '行业',
            'device_type' => '设备类型',
            'new_vs_returning' => '新客vs老客',
        ],
    ],

    'statistics' => [
        'confidence_levels' => [90, 95, 99],
        'minimum_effect_size' => 0.05, // 最小可检测效应量 5%
        'use_bayesian' => false,
    ],

    'scheduling' => [
        'auto_start' => false,
        'auto_complete_on_sample' => true,
        'notification_on_complete' => true,
    ],
];
