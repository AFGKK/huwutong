<?php

// M2-138 API Key 端点级细粒度权限 配置

return [
    /*
    |--------------------------------------------------------------------------
    | SDK 端点定义
    |--------------------------------------------------------------------------
    */
    'endpoints' => [
        'activate' => [
            'methods' => ['POST'],
            'description' => '激活 License',
            'required_permission' => 'read-write',
        ],
        'validate' => [
            'methods' => ['GET'],
            'description' => '验证 License 有效性',
            'required_permission' => 'read-only',
        ],
        'revoke' => [
            'methods' => ['POST'],
            'description' => '吊销 License',
            'required_permission' => 'admin',
        ],
        'check' => [
            'methods' => ['GET'],
            'description' => '检查 License 状态',
            'required_permission' => 'read-only',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 权限级别
    |--------------------------------------------------------------------------
    */
    'permission_levels' => [
        'read-only' => '只读',
        'read-write' => '读写',
        'admin' => '管理',
    ],

    /*
    |--------------------------------------------------------------------------
    | 默认权限
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'endpoint_permissions' => [
            'activate' => ['POST'],
            'validate' => ['GET'],
            'check' => ['GET'],
        ],
        'rate_limit' => 60,      // 每分钟请求数
        'daily_quota' => 10000,  // 每日请求数
    ],

    /*
    |--------------------------------------------------------------------------
    | IP 白名单
    |--------------------------------------------------------------------------
    */
    'ip_whitelist' => [
        'enabled' => env('FGAK_IP_WHITELIST_ENABLED', true),
        'max_ips' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | 审计
    |--------------------------------------------------------------------------
    */
    'audit' => [
        'log_permission_changes' => env('FGAK_AUDIT', true),
        'log_access_denied' => env('FGAK_LOG_DENIED', true),
    ],
];
