<?php

// M2-116 TPM/安全芯片硬件安全绑定 配置

return [
    /*
    |--------------------------------------------------------------------------
    | 启用 TPM 硬件绑定
    |--------------------------------------------------------------------------
    */
    'enabled' => env('TPM_BINDING_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | TPM 2.0 配置
    |--------------------------------------------------------------------------
    */
    'tpm' => [
        'require_ek_cert' => env('TPM_REQUIRE_EK_CERT', true),   // 是否要求 Endorsement Key 证书
        'require_ak' => env('TPM_REQUIRE_AK', true),              // 是否要求 Attestation Key
        'allowed_pcr_selection' => [0, 1, 2, 3, 4, 5, 7],        // 支持的 PCR 索引
        'quote_nonce_length' => 32,                               // Quote 随机数长度
        'max_quote_age_seconds' => 300,                           // Quote 最大有效时间(5分钟)
    ],

    /*
    |--------------------------------------------------------------------------
    | SGX 配置
    |--------------------------------------------------------------------------
    */
    'sgx' => [
        'enabled' => env('TPM_SGX_ENABLED', false),
        'require_quote' => env('TPM_SGX_REQUIRE_QUOTE', true),
        'allowed_tcb_levels' => explode(',', env('TPM_SGX_TCB_LEVELS', 'OK,SW_HARDENING_NEEDED')),
    ],

    /*
    |--------------------------------------------------------------------------
    | 绑定策略
    |--------------------------------------------------------------------------
    */
    'binding' => [
        'max_bindings_per_license' => (int) env('TPM_MAX_BINDINGS', 3),     // 每个License最多绑定设备数
        'allow_software_fallback' => env('TPM_SOFTWARE_FALLBACK', false),   // 是否允许降级到软件指纹
        'auto_bind_on_activate' => env('TPM_AUTO_BIND', false),             // 激活时是否自动绑定
        'require_for_high_security' => env('TPM_REQUIRE_HIGH_SEC', true),   // 高安全场景是否强制要求
    ],

    /*
    |--------------------------------------------------------------------------
    | 认证链
    |--------------------------------------------------------------------------
    */
    'attestation' => [
        'verify_ek_cert_chain' => env('TPM_VERIFY_EK_CHAIN', true),   // 验证 EK 证书链
        'trusted_manufacturers' => [
            'Intel', 'AMD', 'NVIDIA', 'Microsoft', 'Google',
        ],
        'revocation_check' => env('TPM_REVOCATION_CHECK', true),      // 检查吊销列表
    ],

    /*
    |--------------------------------------------------------------------------
    | 验证策略
    |--------------------------------------------------------------------------
    */
    'verification' => [
        'verify_on_every_check' => env('TPM_VERIFY_EVERY_CHECK', false), // 每次验证都执行完整 TPM Quote
        'verify_interval_hours' => (int) env('TPM_VERIFY_INTERVAL', 24), // 间隔验证(非每次)
        'failed_attempts_before_lock' => 5,                              // 失败锁定阈值
        'lockout_duration_minutes' => 30,                                // 锁定时长
    ],
];
