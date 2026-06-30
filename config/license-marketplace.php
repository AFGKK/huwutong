<?php

// M3-81 License 二级市场配置

return [
    'listing' => [
        'allowed_statuses' => ['active', 'suspended'],
        'min_price' => 1,
        'max_price' => 999999,
        'max_active_listings_per_tenant' => 20,
        'auto_expire_days' => 90,
        'require_approval' => env('MARKETPLACE_REQUIRE_APPROVAL', true),
    ],

    'commission' => [
        'rate' => env('MARKETPLACE_COMMISSION_RATE', 0.05), // 5%
        'min_fee' => 1,
        'max_fee' => 1000,
        'collect_from' => 'seller', // seller|buyer
    ],

    'dispute' => [
        'resolution_timeout_hours' => 72,
        'max_disputes_per_transaction' => 1,
        'auto_resolve_days' => 14,
        'evidence_max_files' => 5,
    ],

    'credit' => [
        'initial_score' => 100,
        'min_score_for_listing' => 50,
        'penalty_dispute_lost' => 20,
        'bonus_successful_sale' => 5,
        'rating_range' => [1, 5],
    ],

    'notifications' => [
        'on_listing_approved' => true,
        'on_sale' => true,
        'on_dispute' => true,
    ],
];
