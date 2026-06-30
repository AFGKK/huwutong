<?php

// M3-35 AI 交叉销售推荐引擎配置

return [
    'recommendation' => [
        'strategies' => [
            'usage_based' => '基于使用模式的升级推荐',
            'similar_customers' => '相似客户关联推荐',
            'feature_adoption' => 'Feature Flag使用率推荐',
            'complementary' => '互补产品推荐',
            'popular' => '热销排行推荐',
        ],
        'max_recommendations' => 6,
        'min_confidence' => 0.3,
        'cache_ttl_minutes' => 60,
    ],

    'usage_based' => [
        'upgrade_triggers' => [
            'approaching_device_limit' => 0.8,     // 设备数达上限80%
            'high_activation_rate' => 0.7,         // 激活率>70%
            'expiring_soon' => 30,                  // 30天内到期
            'high_api_usage' => 0.7,                // API用量>70%
            'multiple_modules' => 3,                 // 已使用3+功能模块
        ],
        'lookback_days' => 90,
    ],

    'similar_customers' => [
        'dimensions' => ['industry', 'size', 'region', 'product_mix'],
        'min_similarity' => 0.4,
        'max_candidates' => 100,
    ],

    'scoring' => [
        'weight_usage' => 0.35,
        'weight_similarity' => 0.25,
        'weight_popularity' => 0.20,
        'weight_affinity' => 0.20,
        'decay_days' => 30,
    ],
];
