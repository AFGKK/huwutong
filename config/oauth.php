<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OAuth 登录提供商配置
    |--------------------------------------------------------------------------
    |
    | 在此配置各社交登录提供商的启用状态和凭证。
    | 前端仅显示 enabled = true 的提供商按钮。
    |
    | 各提供商的 client_id / client_secret 通常在 services.php 中配置。
    */
    'providers' => [
        'wechat' => [
            'name' => '微信',
            'enabled' => env('OAUTH_WECHAT_ENABLED', false),
            'icon' => 'wechat',
            'color' => '#07c160',
        ],
        'qq' => [
            'name' => 'QQ',
            'enabled' => env('OAUTH_QQ_ENABLED', false),
            'icon' => 'qq',
            'color' => '#12b7f5',
        ],
        'apple' => [
            'name' => 'Apple',
            'enabled' => env('OAUTH_APPLE_ENABLED', false),
            'icon' => 'apple',
            'color' => '#000000',
        ],
        'google' => [
            'name' => 'Google',
            'enabled' => env('OAUTH_GOOGLE_ENABLED', false),
            'icon' => 'google',
            'color' => '#4285f4',
        ],
        'github' => [
            'name' => 'GitHub',
            'enabled' => env('OAUTH_GITHUB_ENABLED', false),
            'icon' => 'github',
            'color' => '#333333',
        ],
        'alipay' => [
            'name' => '支付宝',
            'enabled' => env('OAUTH_ALIPAY_ENABLED', false),
            'icon' => 'alipay',
            'color' => '#1677ff',
        ],
        'qq' => [
            'name' => 'QQ',
            'enabled' => env('OAUTH_QQ_ENABLED', false),
            'icon' => 'qq',
            'color' => '#12b7f5',
        ],
    ],
];
