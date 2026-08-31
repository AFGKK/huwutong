<?php

// M2-85 页脚导航配置

return [
    /*
    |--------------------------------------------------------------------------
    | 默认页脚链接
    |--------------------------------------------------------------------------
    */
    'default_links' => [
        [
            'label' => '关于我们',
            'type' => 'page',
            'url' => '/about',
            'icon' => null,
            'target' => '_self',
            'group' => 'footer',
            'sort_order' => 10,
            'is_active' => true,
        ],
        [
            'label' => '联系我们',
            'type' => 'page',
            'url' => '/contact',
            'icon' => null,
            'target' => '_self',
            'group' => 'footer',
            'sort_order' => 15,
            'is_active' => true,
        ],
        [
            'label' => '系统状态',
            'type' => 'status',
            'url' => '/build/status',
            'icon' => null,
            'target' => '_self',
            'group' => 'footer',
            'sort_order' => 18,
            'is_active' => true,
        ],
        [
            'label' => '服务条款',
            'type' => 'page',
            'url' => '/terms',
            'icon' => null,
            'target' => '_self',
            'group' => 'bottom',
            'sort_order' => 20,
            'is_active' => true,
        ],
        [
            'label' => '隐私政策',
            'type' => 'page',
            'url' => '/privacy',
            'icon' => null,
            'target' => '_self',
            'group' => 'bottom',
            'sort_order' => 30,
            'is_active' => true,
        ],
        [
            'label' => '安全策略',
            'type' => 'page',
            'url' => '/security-policy',
            'icon' => null,
            'target' => '_self',
            'group' => 'bottom',
            'sort_order' => 40,
            'is_active' => true,
        ],
        [
            'label' => 'Cookie 政策',
            'type' => 'page',
            'url' => '/cookie-policy',
            'icon' => null,
            'target' => '_self',
            'group' => 'bottom',
            'sort_order' => 50,
            'is_active' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 链接类型
    |--------------------------------------------------------------------------
    */
    'link_types' => [
        'page' => '系统页面',
        'custom' => '自定义链接',
        'help' => '帮助中心',
        'api_docs' => 'API 文档',
        'status' => '状态页',
        'social' => '社交媒体',
        'contact' => '联系方式',
    ],

    /*
    |--------------------------------------------------------------------------
    | 社交媒体图标（FontAwesome/Element Plus 图标名）
    |--------------------------------------------------------------------------
    */
    'social_platforms' => [
        'github' => 'GitHub',
        'twitter' => 'Twitter / X',
        'linkedin' => 'LinkedIn',
        'facebook' => 'Facebook',
        'youtube' => 'YouTube',
        'wechat' => '微信',
        'weibo' => '微博',
        'zhihu' => '知乎',
        'bilibili' => 'B站',
    ],

    /*
    |--------------------------------------------------------------------------
    | 最多链接数
    |--------------------------------------------------------------------------
    */
    'max_links' => 20,

    /*
    |--------------------------------------------------------------------------
    | 页脚底部文字（备案号等）
    |--------------------------------------------------------------------------
    */
    'footer_text' => env('FOOTER_TEXT', ''),
    'icp_beian' => env('ICP_BEIAN', ''),
    'police_beian' => env('POLICE_BEIAN', ''),
];
