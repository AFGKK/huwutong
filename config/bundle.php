<?php

// M3-82 多商品合并购买+组合套餐配置

return [
    'bundle' => [
        'max_items_per_bundle' => 20,
        'max_discount_percent' => 50,
        'allowed_billing_periods' => ['monthly', 'quarterly', 'semi_annually', 'annually'],
        'auto_split_orders' => true,
        'inventory' => [
            'track_stock' => true,
            'low_stock_threshold' => 5,
        ],
    ],
];
