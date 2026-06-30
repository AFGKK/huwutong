<?php

// M2-06 支付集成 配置

return [
    /*
    |--------------------------------------------------------------------------
    | 默认支付驱动
    |--------------------------------------------------------------------------
    | 支持: mock, alipay, wechat, stripe
    */
    'driver' => env('PAYMENT_DRIVER', 'mock'),

    /*
    |--------------------------------------------------------------------------
    | 支持的支付渠道
    |--------------------------------------------------------------------------
    | 支持: mock, alipay, wechat, stripe, paypal
    */
    'channels' => [
        'alipay' => [
            'name' => '支付宝',
            'enabled' => env('ALIPAY_ENABLED', false),
            'app_id' => env('ALIPAY_APP_ID', ''),
            'private_key' => env('ALIPAY_PRIVATE_KEY', ''),
            'public_key' => env('ALIPAY_PUBLIC_KEY', ''),
            'notify_url' => env('ALIPAY_NOTIFY_URL', ''),
            'return_url' => env('ALIPAY_RETURN_URL', ''),
            'sandbox' => env('ALIPAY_SANDBOX', true),
        ],

        'wechat' => [
            'name' => '微信支付',
            'enabled' => env('WECHAT_PAY_ENABLED', false),
            'app_id' => env('WECHAT_APP_ID', ''),
            'mch_id' => env('WECHAT_MCH_ID', ''),
            'key' => env('WECHAT_PAY_KEY', ''),
            'cert_path' => env('WECHAT_CERT_PATH', ''),
            'key_path' => env('WECHAT_KEY_PATH', ''),
            'notify_url' => env('WECHAT_NOTIFY_URL', ''),
            'sandbox' => env('WECHAT_PAY_SANDBOX', true),
        ],

        'stripe' => [
            'name' => 'Stripe',
            'enabled' => env('STRIPE_ENABLED', false),
            'key' => env('STRIPE_KEY', ''),
            'secret' => env('STRIPE_SECRET', ''),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', ''),
        ],

        'paypal' => [
            'name' => 'PayPal',
            'enabled' => env('PAYPAL_ENABLED', false),
            'client_id' => env('PAYPAL_CLIENT_ID', ''),
            'client_secret' => env('PAYPAL_CLIENT_SECRET', ''),
            'webhook_id' => env('PAYPAL_WEBHOOK_ID', ''),
            'sandbox' => env('PAYPAL_SANDBOX', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 交易相关
    |--------------------------------------------------------------------------
    */
    'transaction' => [
        'prefix' => env('PAYMENT_TXN_PREFIX', 'TXN-'),
        'expiry_minutes' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook
    |--------------------------------------------------------------------------
    */
    'webhook' => [
        'retry_max_attempts' => 3,
        'retry_delay_minutes' => 5,
        'log_payload' => true,
    ],
];
