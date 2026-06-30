<?php

// M2-159 🛒 秒杀/抢购防护配置

return [
    'flash_sale' => [
        'max_active_per_tenant' => 5,
        'preheat_before_minutes' => 10,
        'stock_reserve_seconds' => 300,
        'queue_timeout_seconds' => 30,
    ],

    'rate_limit' => [
        'token_bucket_capacity' => 100,
        'token_refill_per_second' => 10,
        'max_requests_per_user' => 3,
        'max_orders_per_ip' => 5,
        'block_duration_minutes' => 30,
    ],

    'anti_fraud' => [
        'check_same_device' => true,
        'check_same_ip' => true,
        'check_same_cookie' => true,
        'min_order_interval_seconds' => 3,
        'max_orders_per_device' => 2,
    ],

    'cache' => [
        'stock_key_prefix' => 'flash_stock:',
        'queue_key_prefix' => 'flash_queue:',
        'token_key_prefix' => 'flash_token:',
        'ttl_seconds' => 600,
    ],
];
