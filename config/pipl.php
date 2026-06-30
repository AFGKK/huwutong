<?php

/**
 * PIPL 中国个人信息保护法合规配置 (M3-33b)
 *
 * 参照 GB/T 35273-2020《信息安全技术 个人信息安全规范》
 */
return [
    // 数据保护负责人 (DPO)
    'dpo' => [
        'name' => env('PIPL_DPO_NAME', ''),
        'email' => env('PIPL_DPO_EMAIL', 'dpo@88.huwutong.com'),
        'phone' => env('PIPL_DPO_PHONE', ''),
        'contact_info' => env('PIPL_DPO_CONTACT', ''),
    ],

    // 个人信息分类分级（参照 GB/T 35273-2020）
    'classification' => [
        'L1' => ['label' => '一级（公开）', 'description' => '可公开获取的个人信息'],
        'L2' => ['label' => '二级（内部）', 'description' => '企业内部可访问的个人信息'],
        'L3' => ['label' => '三级（敏感）', 'description' => '敏感个人信息，一旦泄露可能导致人身/财产损害'],
        'L4' => ['label' => '四级（核心）', 'description' => '核心个人信息，一旦泄露可能导致严重危害'],
    ],

    // 敏感个人信息定义（参考 GB/T 35273-2020 附录B）
    'sensitive_fields' => [
        'id_card', 'passport', 'bank_card', 'health_info', 'genetic_data',
        'biometric', 'race', 'ethnicity', 'religion', 'political_view',
        'sexual_orientation', 'criminal_record', 'financial_info',
        'location_tracking', 'children_info', 'password',
    ],

    // 未成年人保护
    'minor' => [
        'age_threshold' => 14,
        'require_parental_consent' => true,
        'data_retention_days' => 180,
        'restricted_features' => ['marketing', 'profiling', 'third_party_sharing'],
    ],

    // 个人信息泄露上报（72小时）
    'breach_notification' => [
        'hours' => 72,
        'notify_authority' => true,
        'notify_affected' => true,
        'channels' => ['email', 'sms', 'phone'],
    ],

    // 跨境数据传输
    'cross_border' => [
        'required_assessment' => true,
        'required_contract' => true,
        'required_certification' => true,
        'allowed_countries' => [],
        'restricted_countries' => [],
    ],

    // 留存期限（天）
    'retention' => [
        'general' => 365 * 3,
        'sensitive' => 365,
        'minor' => 180,
        'deleted' => 90,
    ],

    // 单独同意弹窗
    'consent' => [
        'require_separate_consent' => true,
        'require_opt_in' => true,
        'allow_withdraw' => true,
        'refresh_interval_days' => 180,
    ],
];
