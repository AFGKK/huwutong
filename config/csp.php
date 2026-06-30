<?php

// M1.3-23 CSP 内容安全策略配置

return [

    /*
    |--------------------------------------------------------------------------
    | 默认 CSP 策略
    |--------------------------------------------------------------------------
    |
    | 当数据库无活跃配置时，使用此默认策略。
    | 也可以在 .env 中通过 CSP_POLICY 覆盖。
    |
    */
    'default_policy' => env('CSP_POLICY', "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self' *; frame-ancestors 'none'; base-uri 'self'"),

    /*
    |--------------------------------------------------------------------------
    | 工作模式
    |--------------------------------------------------------------------------
    |
    | enforce: 强制模式，浏览器阻止违规资源（生产环境推荐）
    | report-only: 仅报告不阻止，用于测试和灰度过渡
    |
    */
    'mode' => env('CSP_MODE', 'enforce'),

    /*
    |--------------------------------------------------------------------------
    | 违规报告
    |--------------------------------------------------------------------------
    |
    | 浏览器检测到 CSP 违规时，POST 到 report_uri 端点。
    | enabled: 启用违规收集
    | report_uri: 违规报告端点
    | batch: 违规报告批处理（合并重复违规）
    |
    */
    'reporting' => [
        'enabled' => env('CSP_REPORTING_ENABLED', true),
        'report_uri' => env('CSP_REPORT_URI', '/api/csp-violations/report'),
        'batch' => [
            'enabled' => true,
            'max_batch_size' => 50,
            'flush_interval' => 60, // 秒
        ],
        'retention_days' => env('CSP_VIOLATION_RETENTION', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | 默认指令集合
    |--------------------------------------------------------------------------
    |
    | 新建 CSP 配置时的默认指令值。
    |
    */
    'default_directives' => [
        'default-src' => ["'self'"],
        'script-src' => ["'self'", "'unsafe-inline'", "'unsafe-eval'"],
        'style-src' => ["'self'", "'unsafe-inline'"],
        'img-src' => ["'self'", 'data:', 'blob:'],
        'font-src' => ["'self'", 'data:'],
        'connect-src' => ["'self'", '*'],
        'frame-ancestors' => ["'none'"],
        'base-uri' => ["'self'"],
        'form-action' => ["'self'"],
        'object-src' => ["'none'"],
    ],

    /*
    |--------------------------------------------------------------------------
    | 预设域名白名单
    |--------------------------------------------------------------------------
    |
    | 后台管理中可直接勾选的常用第三方域名。
    |
    */
    'preset_domains' => [
        'cdnjs.cloudflare.com',
        'unpkg.com',
        'fonts.googleapis.com',
        'fonts.gstatic.com',
        'js.stripe.com',
        'api.stripe.com',
        'open.weixin.qq.com',
        'api.weixin.qq.com',
        'www.google-analytics.com',
        'www.googletagmanager.com',
        'oss-cn-hangzhou.aliyuncs.com',
        'huwutong.com',
        '*.huwutong.com',
    ],

    /*
    |--------------------------------------------------------------------------
    | CSP 级别配置（策略宽松程度）
    |--------------------------------------------------------------------------
    |
    | strict: 严格模式，禁止 inline script/style，适合高安全需求
    | standard: 标准模式，允许 unsafe-inline（管理后台需要）
    | custom: 自定义模式，完全由用户配置
    |
    */
    'levels' => [
        'strict' => [
            'label' => '严格模式',
            'description' => '禁止 inline script/style，script-src 必须包含 nonce 或 hash',
            'directives' => [
                'default-src' => ["'self'"],
                'script-src' => ["'self'"],
                'style-src' => ["'self'"],
                'img-src' => ["'self'", 'data:'],
                'font-src' => ["'self'"],
                'connect-src' => ["'self'"],
                'frame-ancestors' => ["'none'"],
                'base-uri' => ["'self'"],
                'form-action' => ["'self'"],
                'object-src' => ["'none'"],
            ],
        ],
        'standard' => [
            'label' => '标准模式',
            'description' => '允许 unsafe-inline script/style，兼容 Element Plus 等前端框架',
            'directives' => [
                'default-src' => ["'self'"],
                'script-src' => ["'self'", "'unsafe-inline'", "'unsafe-eval'"],
                'style-src' => ["'self'", "'unsafe-inline'"],
                'img-src' => ["'self'", 'data:', 'blob:'],
                'font-src' => ["'self'", 'data:'],
                'connect-src' => ["'self'", '*'],
                'frame-ancestors' => ["'none'"],
                'base-uri' => ["'self'"],
                'form-action' => ["'self'"],
                'object-src' => ["'none'"],
            ],
        ],
    ],
];
