<?php

// OEM 白标系统配置 (M3-03)

return [

    /*
    |--------------------------------------------------------------------------
    | OEM 套餐定义
    |--------------------------------------------------------------------------
    */
    'tiers' => [
        'basic' => [
            'name' => 'OEM Basic',
            'name_zh' => '基础白标',
            'price_monthly' => 0,
            'price_yearly' => 0,
            'features' => [
                'custom_logo' => true,
                'brand_colors' => true,
                'brand_name_customization' => true,
                'custom_favicon' => true,
                'custom_domain' => false,
                'ssl_auto' => false,
                'branded_login' => false,
                'custom_email_domain' => false,
                'remove_branding' => false,
                'api_whitelabel' => false,
                'custom_css' => false,
                'custom_html' => false,
                'multi_locale_branding' => false,
                'priority_support' => false,
                'max_domains' => 0,
                'max_themes' => 1,
            ],
        ],
        'business' => [
            'name' => 'OEM Business',
            'name_zh' => '商业白标',
            'price_monthly' => 299,
            'price_yearly' => 2999,
            'features' => [
                'custom_logo' => true,
                'brand_colors' => true,
                'brand_name_customization' => true,
                'custom_favicon' => true,
                'custom_domain' => true,
                'ssl_auto' => true,
                'branded_login' => true,
                'custom_email_domain' => true,
                'remove_branding' => true,
                'api_whitelabel' => false,
                'custom_css' => true,
                'custom_html' => false,
                'multi_locale_branding' => true,
                'priority_support' => false,
                'max_domains' => 2,
                'max_themes' => 3,
            ],
        ],
        'enterprise' => [
            'name' => 'OEM Enterprise',
            'name_zh' => '企业白标',
            'price_monthly' => 999,
            'price_yearly' => 9999,
            'features' => [
                'custom_logo' => true,
                'brand_colors' => true,
                'brand_name_customization' => true,
                'custom_favicon' => true,
                'custom_domain' => true,
                'ssl_auto' => true,
                'branded_login' => true,
                'custom_email_domain' => true,
                'remove_branding' => true,
                'api_whitelabel' => true,
                'custom_css' => true,
                'custom_html' => true,
                'multi_locale_branding' => true,
                'priority_support' => true,
                'max_domains' => 10,
                'max_themes' => 10,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 品牌化登录页配置 (M3-47)
    |--------------------------------------------------------------------------
    */
    'branded_login' => [
        // 默认登录页配置
        'default_title' => 'Login',
        'default_subtitle' => 'Welcome to {brand_name}',
        'allowed_background_types' => ['color', 'gradient', 'image'],
        'max_logo_height' => 80,
        'max_bg_image_size_kb' => 2048,
    ],

    /*
    |--------------------------------------------------------------------------
    | 自定义域名配置
    |--------------------------------------------------------------------------
    */
    'custom_domain' => [
        'cname_target' => env('CNAME_TARGET', 'app.huwutong.com.'),  // 平台CDN域名
        'verification_methods' => ['cname', 'txt'],
        'ssl_provider' => env('SSL_PROVIDER', 'letsencrypt'),
        'auto_ssl' => env('AUTO_SSL', true),
        'dns_check_interval_seconds' => 300, // DNS验证检查间隔
    ],

    /*
    |--------------------------------------------------------------------------
    | 邮件白标域名配置 (M2-80)
    |--------------------------------------------------------------------------
    */
    'email_whitelabel' => [
        'dkim_selector' => env('DKIM_SELECTOR', 'hwt'),
        'spf_enabled' => true,
        'dmarc_enabled' => true,
        'tracking_domain_enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | 移除品牌标识
    |--------------------------------------------------------------------------
    */
    'remove_branding' => [
        'footer_text' => null, // null = 可自定义
        'hide_powered_by' => true,
        'hide_platform_branding' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | API 白标
    |--------------------------------------------------------------------------
    */
    'api_whitelabel' => [
        'custom_api_base_url' => true,
        'custom_error_messages' => true,
        'custom_response_headers' => true,
    ],
];
