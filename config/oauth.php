<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OAuth 登录提供商配置
    |--------------------------------------------------------------------------
    |
    | 前端仅显示 enabled = true 且已实现跳转换票的提供商。
    | 当前已实现：wechat / qq / google / github
    |
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
    ],
];
