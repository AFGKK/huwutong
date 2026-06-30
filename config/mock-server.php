<?php

// M1.4-59 API Mock Server 配置

return [

    /*
    |--------------------------------------------------------------------------
    | Mock Server 开关
    |--------------------------------------------------------------------------
    */
    'enabled' => env('MOCK_SERVER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Mock 端点基础路径
    |--------------------------------------------------------------------------
    */
    'base_path' => env('MOCK_SERVER_PATH', '/api/mock'),

    /*
    |--------------------------------------------------------------------------
    | 默认响应配置
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        // 默认响应延迟（毫秒）
        'delay_ms' => (int) env('MOCK_DEFAULT_DELAY', 50),
        // 默认错误率（百分比 0-100）
        'error_rate' => (int) env('MOCK_DEFAULT_ERROR_RATE', 0),
        // 默认状态码
        'status_code' => 200,
    ],

    /*
    |--------------------------------------------------------------------------
    | 预建 Mock 规则（基于 OpenAPI 文档自动生成）
    |--------------------------------------------------------------------------
    */
    'prebuilt_rules' => [
        [
            'method' => 'POST',
            'path' => '/api/license/activate',
            'status_code' => 200,
            'response' => [
                'success' => true,
                'data' => [
                    'license_key' => 'MOCK-XXXX-XXXX-XXXX',
                    'expires_at' => now()->addYear()->toIso8601String(),
                    'device_id' => 1,
                    'activated_at' => now()->toIso8601String(),
                ],
            ],
            'description' => 'License 激活成功',
        ],
        [
            'method' => 'POST',
            'path' => '/api/license/validate',
            'status_code' => 200,
            'response' => [
                'success' => true,
                'data' => [
                    'is_valid' => true,
                    'license_key' => 'MOCK-XXXX-XXXX-XXXX',
                    'status' => 'active',
                    'days_remaining' => 365,
                    'features' => ['basic', 'premium'],
                ],
            ],
            'description' => 'License 验证成功',
        ],
        [
            'method' => 'GET',
            'path' => '/api/products',
            'status_code' => 200,
            'response' => [
                'success' => true,
                'data' => [
                    ['id' => 1, 'name' => 'Mock 产品 A', 'slug' => 'mock-a', 'price' => 99],
                    ['id' => 2, 'name' => 'Mock 产品 B', 'slug' => 'mock-b', 'price' => 299],
                ],
            ],
            'description' => '产品列表',
        ],
        [
            'method' => 'POST',
            'path' => '/api/license/activate',
            'status_code' => 400,
            'response' => [
                'success' => false,
                'error' => ['code' => 'DEVICE_LIMIT', 'message' => '设备数量已达上限'],
            ],
            'description' => '激活失败-设备超限',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 响应模板
    |--------------------------------------------------------------------------
    */
    'response_templates' => [
        'success' => ['success' => true, 'data' => null],
        'error' => ['success' => false, 'error' => ['code' => 'ERROR', 'message' => '默认错误']],
        'validation_error' => ['success' => false, 'errors' => ['field' => ['验证失败']]],
        'not_found' => ['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => '资源不存在']],
        'unauthorized' => ['success' => false, 'error' => ['code' => 'UNAUTHORIZED', 'message' => '未授权']],
    ],

    /*
    |--------------------------------------------------------------------------
    | Docker 部署配置
    |--------------------------------------------------------------------------
    */
    'docker' => [
        'image' => 'huwutong/mock-server',
        'port' => env('MOCK_SERVER_PORT', 8081),
    ],

];
