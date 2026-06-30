<?php

// M3-28 开放平台 / 应用市场配置

return [
    'developer' => [
        'auto_approve' => env('OPENPLATFORM_AUTO_APPROVE', false),
        'required_fields' => ['display_name', 'email'],
        'max_apps_per_developer' => 50,
        'verification_required' => true,
    ],

    'app' => [
        'review_required' => true,
        'max_versions_per_app' => 20,
        'allowed_categories' => [
            'integration' => '集成扩展',
            'automation' => '自动化',
            'analytics' => '数据分析',
            'security' => '安全合规',
            'billing' => '计费财务',
            'other' => '其他',
        ],
        'pricing_models' => ['free', 'paid', 'freemium', 'subscription'],
        'max_screenshots' => 10,
        'max_description_length' => 5000,
    ],

    'marketplace' => [
        'public_access' => true,
        'featured_app_limit' => 6,
        'recent_app_days' => 30,
        'allow_ratings' => true,
        'allow_reviews' => true,
    ],

    'security' => [
        'require_api_key' => true,
        'permission_scope' => 'marketplace:app',
        'rate_limit_per_minute' => 60,
        'webhook_url_validation' => true,
    ],

    'commission' => [
        'platform_fee_percentage' => env('MARKETPLACE_FEE', 20),
        'payout_minimum' => 100,
        'payout_schedule' => 'monthly',
    ],
];
