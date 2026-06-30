<?php

// M3-37 AI 客户行为聚类配置

return [
    'clustering' => [
        'algorithm' => 'kmeans', // kmeans|dbscan|hierarchical
        'num_clusters' => 5,
        'min_cluster_size' => 10,
        'lookback_days' => 90,
        'auto_retrain_days' => 7,
    ],

    'dimensions' => [
        'usage' => [
            'license_count' => 'License数量',
            'activation_rate' => '激活率',
            'device_count' => '设备数',
            'api_calls' => 'API调用量',
            'modules_used' => '使用模块数',
        ],
        'value' => [
            'total_spend' => '总消费',
            'avg_order_value' => '平均订单价值',
            'subscription_months' => '订阅月数',
        ],
        'engagement' => [
            'login_frequency' => '登录频率',
            'support_tickets' => '工单数',
            'feature_adoption_rate' => '功能采用率',
        ],
    ],

    'segments' => [
        'high_value_active' => ['label' => '高价值活跃客户', 'color' => '#52c41a', 'priority' => 1],
        'growth_potential' => ['label' => '成长潜力客户', 'color' => '#1890ff', 'priority' => 2],
        'at_risk' => ['label' => '流失风险客户', 'color' => '#fa8c16', 'priority' => 3],
        'new_onboarding' => ['label' => '新手引导客户', 'color' => '#722ed1', 'priority' => 4],
        'low_engagement' => ['label' => '低活跃客户', 'color' => '#d9d9d9', 'priority' => 5],
    ],

    'actions' => [
        'high_value_active' => ['priority_support', 'upsell', 'referral_program'],
        'growth_potential' => ['feature_education', 'case_study', 'webinar_invite'],
        'at_risk' => ['discount_offer', 'checkin_call', 'success_story'],
        'new_onboarding' => ['onboarding_guide', 'training_session', 'welcome_series'],
        'low_engagement' => ['reactivation_email', 'product_tour', 'usage_tips'],
    ],
];
