<?php

/**
 * M2-15 更新签名验证 + 回滚机制 + 区域灰度发布配置
 *
 * 增强 M2-14 自动更新系统的安全性和分发策略。
 */
return [

    /*
    |--------------------------------------------------------------------------
    | 签名验证配置
    |--------------------------------------------------------------------------
    */
    'signing' => [
        // 签名算法（ed25519 / rsa-sha256）
        'algorithm' => env('UPDATE_SIGNER_ALGORITHM', 'ed25519'),

        // Ed25519 密钥对
        'ed25519_private_key' => env('UPDATE_ED25519_PRIVATE_KEY'),
        'ed25519_public_key' => env('UPDATE_ED25519_PUBLIC_KEY'),

        // RSA 密钥对（兼容模式）
        'rsa_private_key' => env('UPDATE_RSA_PRIVATE_KEY'),
        'rsa_public_key' => env('UPDATE_RSA_PUBLIC_KEY'),

        // 公钥版本（用于客户端缓存更新）
        'public_key_version' => env('UPDATE_PUBLIC_KEY_VERSION', 1),

        // 是否强制验证签名（生产环境建议 true）
        'verify_required' => env('UPDATE_SIGNER_VERIFY_REQUIRED', true),

        // 验证失败后的行为
        'on_verify_fail' => env('UPDATE_SIGNER_ON_FAIL', 'block'), // block | warn | allow
    ],

    /*
    |--------------------------------------------------------------------------
    | 回滚机制配置
    |--------------------------------------------------------------------------
    */
    'rollback' => [
        // 是否启用自动回滚
        'auto_rollback' => env('UPDATE_AUTO_ROLLBACK', true),

        // 自动回滚触发条件
        'triggers' => [
            'client_crash_rate' => 0.05,       // 客户端崩溃率 > 5%
            'activation_failure_rate' => 0.10,  // 激活失败率 > 10%
            'report_timeout_hours' => 24,       // 24小时内无健康报告
        ],

        // 最大回滚保留版本数
        'max_versions' => env('UPDATE_ROLLBACK_MAX_VERSIONS', 5),

        // 回滚审批流程（true=需要管理员确认）
        'require_approval' => env('UPDATE_ROLLBACK_REQUIRE_APPROVAL', true),

        // 回滚窗口期（发布后N小时内可回滚）
        'window_hours' => env('UPDATE_ROLLBACK_WINDOW_HOURS', 48),
    ],

    /*
    |--------------------------------------------------------------------------
    | 区域灰度发布配置
    |--------------------------------------------------------------------------
    */
    'gray_release' => [
        // 是否启用灰度发布
        'enabled' => env('UPDATE_GRAY_RELEASE_ENABLED', true),

        // 灰度策略
        'strategies' => [
            'region' => [
                'label' => '按区域灰度',
                'description' => '按地理位置区域逐步发布',
            ],
            'percentage' => [
                'label' => '按百分比灰度',
                'description' => '按用户比例逐步放量',
            ],
            'whitelist' => [
                'label' => '白名单灰度',
                'description' => '仅白名单租户可更新',
            ],
            'tenant_tag' => [
                'label' => '按租户标签灰度',
                'description' => '按租户自定义标签分组发布',
            ],
        ],

        // 灰度阶段
        'stages' => [
            'canary' => [
                'label' => '金丝雀发布',
                'percentage' => 5,
                'description' => '5% 内部/测试用户',
                'duration_hours' => 24,
            ],
            'beta' => [
                'label' => 'Beta 发布',
                'percentage' => 20,
                'description' => '20% 早期用户',
                'duration_hours' => 48,
            ],
            'wide' => [
                'label' => '广泛发布',
                'percentage' => 50,
                'description' => '50% 用户',
                'duration_hours' => 72,
            ],
            'full' => [
                'label' => '全量发布',
                'percentage' => 100,
                'description' => '100% 用户',
                'duration_hours' => 0,
            ],
        ],

        // 灰度指标检查
        'metrics' => [
            'check_interval_minutes' => 15,
            'max_error_rate' => 0.03,       // 最大错误率 3%
            'min_report_rate' => 0.01,      // 最少上报率 1%
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 验证记录保留天数
    |--------------------------------------------------------------------------
    */
    'retention_days' => env('UPDATE_VERIFICATION_RETENTION_DAYS', 90),
];
