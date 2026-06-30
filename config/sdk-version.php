<?php

/**
 * M2-16 SDK 版本兼容策略配置
 *
 * 定义多语言SDK的多版本共存策略、兼容矩阵、强制升级规则。
 * 基于 M2-34 统一错误码体系。
 */
return [

    /*
    |--------------------------------------------------------------------------
    | 兼容策略
    |--------------------------------------------------------------------------
    */
    'strategy' => [
        // 最大并行支持的版本数（超过此数的老版本标记为deprecated）
        'max_parallel_versions' => env('SDK_MAX_PARALLEL_VERSIONS', 3),

        // 版本废弃后的宽限期（天）
        'deprecation_grace_days' => env('SDK_DEPRECATION_GRACE_DAYS', 90),

        // 强制升级前的提醒天数
        'force_upgrade_warn_days' => env('SDK_FORCE_UPGRADE_WARN_DAYS', 30),

        // 最小支持的SDK主版本号（低于此版本将拒绝服务）
        'minimum_major_version' => env('SDK_MINIMUM_MAJOR_VERSION', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | 兼容矩阵
    |--------------------------------------------------------------------------
    |
    | 各SDK版本的兼容性定义：
    | - 跨版本API兼容规则
    | - 错误码兼容版本
    | - 推荐的升级路径
    |
    */
    'compatibility' => [
        // API 版本兼容规则
        'api_versions' => [
            'v1' => [
                'min_sdk_version' => '1.0.0',
                'max_sdk_version' => '1.x',
                'status' => 'stable',
                'deprecated_at' => null,
                'sunset_at' => null,
            ],
            'v2' => [
                'min_sdk_version' => '2.0.0',
                'max_sdk_version' => '2.x',
                'status' => 'beta',
                'deprecated_at' => null,
                'sunset_at' => null,
            ],
        ],

        // 错误码兼容版本
        'error_code_since' => '1.0.0',
    ],

    /*
    |--------------------------------------------------------------------------
    | 版本生命周期阶段
    |--------------------------------------------------------------------------
    */
    'lifecycle' => [
        'preview' => [
            'label' => '预览版',
            'description' => '内测预览，不建议生产使用',
            'allow_production' => false,
        ],
        'stable' => [
            'label' => '稳定版',
            'description' => '推荐生产使用',
            'allow_production' => true,
        ],
        'deprecated' => [
            'label' => '已废弃',
            'description' => '不再推荐使用，将在宽限期后停止服务',
            'allow_production' => false,
            'auto_upgrade_notify' => true,
        ],
        'sunset' => [
            'label' => '已停服',
            'description' => '不再提供服务，必须升级',
            'allow_production' => false,
            'returns_error' => 'SDK_VERSION_SUNSET',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 强制升级规则
    |--------------------------------------------------------------------------
    */
    'force_upgrade' => [
        'enabled' => env('SDK_FORCE_UPGRADE_ENABLED', true),

        // 触发强制升级的条件
        'triggers' => [
            'security_vulnerability' => true,     // 安全漏洞
            'api_breaking_change' => true,         // API 不兼容变更
            'error_code_deprecated' => true,       // 错误码废弃
            'protocol_change' => true,              // 通信协议变更
        ],

        // 强制升级时返回的错误码（M2-34）
        'error_code' => 'SDK_UPGRADE_REQUIRED',

        // 强制升级时的HTTP状态码
        'http_status' => 426,
    ],
];
