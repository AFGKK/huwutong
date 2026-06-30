<?php

// M2-100 竞品对比页 配置

return [
    /*
    |--------------------------------------------------------------------------
    | 竞品列表
    |--------------------------------------------------------------------------
    */
    'competitors' => [
        'cryptlex' => [
            'name' => 'Cryptlex',
            'website' => 'https://cryptlex.com',
            'logo' => '/images/competitors/cryptlex.svg',
            'description' => '基于云的 License 管理平台',
        ],
        'localazy' => [
            'name' => 'Localazy',
            'website' => 'https://localazy.com',
            'logo' => '/images/competitors/localazy.svg',
            'description' => '软件本地化 + License 管理',
        ],
        'keygen' => [
            'name' => 'Keygen.sh',
            'website' => 'https://keygen.sh',
            'logo' => '/images/competitors/keygen.svg',
            'description' => '开发者优先的 License API',
        ],
        'licensespring' => [
            'name' => 'LicenseSpring',
            'website' => 'https://licensespring.com',
            'logo' => '/images/competitors/licensespring.svg',
            'description' => '企业级 License 管理',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 对比维度
    |--------------------------------------------------------------------------
    */
    'dimensions' => [
        'pricing' => [
            'label' => '定价模式',
            'type' => 'text',
        ],
        'self_hosted' => [
            'label' => '自托管部署',
            'type' => 'boolean',
        ],
        'offline_auth' => [
            'label' => '离线授权',
            'type' => 'boolean',
        ],
        'device_fingerprint' => [
            'label' => '设备指纹',
            'type' => 'boolean',
        ],
        'seat_pooling' => [
            'label' => '席位池浮动',
            'type' => 'boolean',
        ],
        'api_sdk' => [
            'label' => '多语言 SDK',
            'type' => 'text',
        ],
        'webhook' => [
            'label' => 'Webhook',
            'type' => 'boolean',
        ],
        'ai_features' => [
            'label' => 'AI 智能功能',
            'type' => 'boolean',
        ],
        'white_label' => [
            'label' => 'OEM 白标',
            'type' => 'boolean',
        ],
        'multi_tenant' => [
            'label' => '多租户',
            'type' => 'boolean',
        ],
        'china_optimized' => [
            'label' => '中国优化',
            'type' => 'boolean',
        ],
        'openapi' => [
            'label' => 'OpenAPI 3.0',
            'type' => 'boolean',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 对比数据（互物通 vs 竞品）
    |--------------------------------------------------------------------------
    | true = 支持, false = 不支持, string = 描述
    |--------------------------------------------------------------------------
    */
    'comparison_data' => [
        'pricing' => [
            'huwutong' => '免费增值 + 按需付费',
            'cryptlex' => '按 License 数',
            'localazy' => '按项目数',
            'keygen' => '按 API 调用量',
            'licensespring' => '按 License 数',
        ],
        'self_hosted' => [
            'huwutong' => true,
            'cryptlex' => true,
            'localazy' => false,
            'keygen' => false,
            'licensespring' => true,
        ],
        'offline_auth' => [
            'huwutong' => true,
            'cryptlex' => true,
            'localazy' => false,
            'keygen' => true,
            'licensespring' => true,
        ],
        'device_fingerprint' => [
            'huwutong' => true,
            'cryptlex' => true,
            'localazy' => false,
            'keygen' => false,
            'licensespring' => true,
        ],
        'seat_pooling' => [
            'huwutong' => true,
            'cryptlex' => true,
            'localazy' => false,
            'keygen' => false,
            'licensespring' => false,
        ],
        'api_sdk' => [
            'huwutong' => 'PHP/Node/Python/Go/Java/C#/Flutter',
            'cryptlex' => 'JS/Python/C#/Java',
            'localazy' => 'JS/Python/PHP',
            'keygen' => 'JS/Ruby/Python/Go',
            'licensespring' => 'JS/C#/Java/Python',
        ],
        'webhook' => [
            'huwutong' => true,
            'cryptlex' => true,
            'localazy' => true,
            'keygen' => true,
            'licensespring' => true,
        ],
        'ai_features' => [
            'huwutong' => true,
            'cryptlex' => false,
            'localazy' => false,
            'keygen' => false,
            'licensespring' => false,
        ],
        'white_label' => [
            'huwutong' => true,
            'cryptlex' => true,
            'localazy' => false,
            'keygen' => false,
            'licensespring' => true,
        ],
        'multi_tenant' => [
            'huwutong' => true,
            'cryptlex' => true,
            'localazy' => false,
            'keygen' => true,
            'licensespring' => false,
        ],
        'china_optimized' => [
            'huwutong' => true,
            'cryptlex' => false,
            'localazy' => false,
            'keygen' => false,
            'licensespring' => false,
        ],
        'openapi' => [
            'huwutong' => true,
            'cryptlex' => true,
            'localazy' => true,
            'keygen' => true,
            'licensespring' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SEO
    |--------------------------------------------------------------------------
    */
    'seo' => [
        'title' => '互物通 vs Cryptlex vs Localazy vs Keygen — License 管理平台对比',
        'description' => '互物通与主流 License 管理平台的功能对比矩阵，涵盖定价、离线授权、设备指纹、SDK支持、AI功能等12个维度。',
        'keywords' => 'License管理对比,Cryptlex替代,Keygen替代,软件授权平台',
    ],
];
