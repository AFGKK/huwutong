<?php

/**
 * M2-17 SDK完整性自检 + 远程自毁配置
 *
 * SDK 文件完整性校验、篡改检测、远程销毁命令管理。
 * 依赖 M2-16 SDK版本兼容策略。
 */
return [

    /*
    |--------------------------------------------------------------------------
    | 完整性校验配置
    |--------------------------------------------------------------------------
    */
    'integrity' => [
        // 是否启用完整性校验
        'enabled' => env('SDK_INTEGRITY_ENABLED', true),

        // 校验算法（sha256 / sha512 / md5）
        'algorithm' => env('SDK_INTEGRITY_ALGORITHM', 'sha256'),

        // 校验频率（秒），SDK定期调用一次自检
        'check_interval' => env('SDK_INTEGRITY_CHECK_INTERVAL', 86400), // 24h

        // 允许的校验失败次数阈值（超过此数触发告警）
        'failure_threshold' => env('SDK_INTEGRITY_FAILURE_THRESHOLD', 3),

        // 校验失败后的宽限期（秒），超过仍不通过则触发自毁
        'grace_period' => env('SDK_INTEGRITY_GRACE_PERIOD', 3600), // 1h

        // 校验失败自动告警
        'alert_on_failure' => env('SDK_INTEGRITY_ALERT_ON_FAILURE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | 远程自毁配置
    |--------------------------------------------------------------------------
    */
    'self_destruct' => [
        // 是否启用远程自毁
        'enabled' => env('SDK_SELF_DESTRUCT_ENABLED', true),

        // 自毁类型
        'types' => [
            'integrity_failure' => '完整性校验连续失败',
            'remote_command' => '管理员远程下发销毁命令',
            'license_revoked' => '关联License被吊销',
            'device_blacklisted' => '设备被加入黑名单',
            'version_deprecated' => 'SDK版本已废弃超过宽限期',
        ],

        // 自毁模式
        'modes' => [
            'soft' => [
                'label' => '软销毁',
                'description' => '停止授权验证功能，保留基础通信能力',
                'sdk_behavior' => '返回SDK_INTEGRITY_FAILED错误，允许心跳上报',
            ],
            'hard' => [
                'label' => '硬销毁',
                'description' => '完全停止所有功能，包括API通信',
                'sdk_behavior' => '所有API调用返回SDK_DESTROYED错误，停止心跳',
            ],
        ],

        // 默认自毁模式
        'default_mode' => env('SDK_SELF_DESTRUCT_MODE', 'soft'),

        // 自毁命令有效期（秒），过期未同步的命令自动失效
        'command_ttl' => env('SDK_SELF_DESTRUCT_COMMAND_TTL', 2592000), // 30天

        // 是否支持批量自毁
        'batch_enabled' => env('SDK_SELF_DESTRUCT_BATCH_ENABLED', true),

        // 批量自毁最大数量
        'batch_max' => env('SDK_SELF_DESTRUCT_BATCH_MAX', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | SDK受保护文件清单
    |--------------------------------------------------------------------------
    |
    | SDK 中需要进行完整性校验的核心文件路径模式。
    | SDK 客户端根据此清单计算文件哈希并上报。
    |
    */
    'protected_files' => [
        'php' => [
            'src/Client.php',
            'src/Auth.php',
            'src/Verifier.php',
            'src/Cache.php',
            'src/Exception.php',
        ],
        'node' => [
            'src/client.js',
            'src/auth.js',
            'src/verifier.js',
            'src/cache.js',
        ],
        'python' => [
            'huwutong_sdk/client.py',
            'huwutong_sdk/auth.py',
            'huwutong_sdk/verifier.py',
            'huwutong_sdk/cache.py',
        ],
        'go' => [
            'client.go',
            'auth.go',
            'verifier.go',
            'cache.go',
        ],
        'java' => [
            'src/main/java/com/huwutong/sdk/HWTClient.java',
            'src/main/java/com/huwutong/sdk/HWTAuth.java',
            'src/main/java/com/huwutong/sdk/HWTVerifier.java',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SDK上报端点
    |--------------------------------------------------------------------------
    |
    | SDK定期上报完整性检查结果和心跳的API端点配置。
    |
    */
    'reporting' => [
        // SDK上报完整性检查结果
        'check_endpoint' => '/api/sdk/integrity/check',

        // SDK轮询是否有待处理的销毁命令
        'poll_endpoint' => '/api/sdk/integrity/poll-destroy',

        // SDK确认销毁命令已执行
        'confirm_endpoint' => '/api/sdk/integrity/confirm-destroy',

        // SDK心跳（含完整性状态）
        'heartbeat_endpoint' => '/api/sdk/integrity/heartbeat',
    ],
];
