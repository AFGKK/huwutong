<?php

// M3-83 多币种商品定价配置

return [
    'enabled' => env('MULTI_CURRENCY_PRICING_ENABLED', true),

    'currencies' => [
        'supported' => ['CNY', 'USD', 'EUR'],
        'default' => 'CNY',
        'display' => 'auto', // auto | preferred | all
    ],

    'conversion' => [
        'strategy' => 'stored_first', // stored_first | auto_convert | manual_only
        'auto_convert_on_save' => true,
        'fallback_to_convert' => true,
    ],

    'display' => [
        'show_currency_selector' => true,
        'show_all_prices' => false,
        'format_locale' => true,
    ],

    'checkout' => [
        'allow_currency_selection' => true,
        'auto_detect_from_location' => true,
        'default_on_first_visit' => 'CNY',
    ],

    'cache' => [
        'prices_ttl' => 3600,
        'sku_prices_ttl' => 1800,
    ],
];
