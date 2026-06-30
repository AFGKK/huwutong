<?php

// M3-81 License 转售/二级市场配置

return [
    'listing' => [
        'commission_rate' => env('RESALE_COMMISSION_RATE', 5.00), // 平台抽成 %
        'max_active_listings_per_seller' => 10,
        'default_duration_days' => 90,
        'max_duration_days' => 180,
        'min_price' => 1,
        'allowed_currencies' => ['CNY', 'USD', 'EUR'],
        'price_discovery' => [
            'enabled' => true,          // 市场供需定价
            'min_price_multiplier' => 0.5,  // 最低价为原价的50%
        ],
    ],

    'transaction' => [
        'escrow_enabled' => true,
        'escrow_hold_days' => 3,        // 资金托管天数
        'auto_release_on_verify' => true,
        'max_dispute_days' => 14,       // 争议申诉期限
    ],

    'review' => [
        'required' => true,
        'auto_approve_trusted_sellers' => false,
        'auto_approve_threshold_score' => 80, // 信任分高于此值自动审核
    ],

    'dispute' => [
        'max_dispute_days' => 14,
        'resolution_timeout_days' => 7,
        'arbitration_fee' => 0,
    ],

    'credit_score' => [
        'seller' => [
            'initial' => 60,
            'increase_on_sale' => 5,
            'decrease_on_dispute' => 20,
            'decrease_on_cancel' => 10,
        ],
        'buyer' => [
            'initial' => 60,
            'increase_on_purchase' => 2,
            'decrease_on_dispute' => 15,
            'decrease_on_cancel' => 10,
        ],
    ],
];
