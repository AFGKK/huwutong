<?php

// M2-87 Postman Collection + 预配置环境 配置

return [
    /*
    |--------------------------------------------------------------------------
    | Collection 信息
    |--------------------------------------------------------------------------
    */
    'collection' => [
        'name' => env('POSTMAN_COLLECTION_NAME', '互物通 API'),
        'description' => '互物通企业授权管理系统 API - 官方 Postman Collection',
        'base_url' => env('APP_URL', 'http://localhost:8000'),
        'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
    ],

    /*
    |--------------------------------------------------------------------------
    | 环境变量
    |--------------------------------------------------------------------------
    */
    'environments' => [
        'development' => [
            'name' => '开发环境',
            'values' => [
                ['key' => 'base_url', 'value' => 'http://localhost:8000/api', 'type' => 'default'],
                ['key' => 'api_key', 'value' => 'your_api_key_here', 'type' => 'secret'],
                ['key' => 'license_key', 'value' => 'HWT-DEV-XXXX-XXXX', 'type' => 'default'],
            ],
        ],
        'staging' => [
            'name' => '预发布环境',
            'values' => [
                ['key' => 'base_url', 'value' => 'https://staging.api.huwutong.com/api', 'type' => 'default'],
                ['key' => 'api_key', 'value' => 'your_api_key_here', 'type' => 'secret'],
                ['key' => 'license_key', 'value' => 'HWT-STAGING-XXXX-XXXX', 'type' => 'default'],
            ],
        ],
        'production' => [
            'name' => '生产环境',
            'values' => [
                ['key' => 'base_url', 'value' => 'https://api.huwutong.com/api', 'type' => 'default'],
                ['key' => 'api_key', 'value' => 'your_api_key_here', 'type' => 'secret'],
                ['key' => 'license_key', 'value' => 'HWT-PROD-XXXX-XXXX', 'type' => 'default'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 包含的 API 分组
    |--------------------------------------------------------------------------
    | 留空表示包含所有端点
    */
    'include_groups' => [
        'License', 'Device', 'Customer', 'Billing',
        'API Keys', 'Webhook', 'Product', 'Subscription',
    ],

    /*
    |--------------------------------------------------------------------------
    | 示例请求
    |--------------------------------------------------------------------------
    */
    'examples' => [
        [
            'name' => '激活 License',
            'method' => 'POST',
            'path' => '/license/activate',
            'headers' => ['Content-Type: application/json'],
            'body' => json_encode([
                'license_key' => '{{license_key}}',
                'fingerprint' => '{{fingerprint}}',
                'components' => [
                    'mac' => '00:1A:2B:3C:4D:5E',
                    'cpu_id' => 'CPU-XXXX-XXXX',
                    'disk_sn' => 'DISK-SN-XXXX',
                ],
            ]),
        ],
        [
            'name' => '验证 License',
            'method' => 'POST',
            'path' => '/license/validate',
            'headers' => ['Content-Type: application/json'],
            'body' => json_encode([
                'license_key' => '{{license_key}}',
                'fingerprint' => '{{fingerprint}}',
            ]),
        ],
    ],
];
