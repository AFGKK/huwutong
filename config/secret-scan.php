<?php

// M1.3-29 密钥泄露扫描配置

return [
    /*
    |--------------------------------------------------------------------------
    | 扫描开关
    |--------------------------------------------------------------------------
    */
    'enabled' => env('SECRET_SCAN_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | 扫描模式
    |--------------------------------------------------------------------------
    |
    | pre-commit: 仅 pre-commit hook 拦截
    | ci: 仅 CI 流水线扫描
    | full: pre-commit + CI + 定时仓库扫描 + 日志扫描
    |
    */
    'mode' => env('SECRET_SCAN_MODE', 'full'),

    /*
    |--------------------------------------------------------------------------
    | 检测模式（正则匹配）
    |--------------------------------------------------------------------------
    |
    | 当扫描到这些模式时触发告警。
    |
    */
    'patterns' => [
        // Stripe
        'sk_live_' => 'Stripe Live Secret Key',
        'pk_live_' => 'Stripe Live Publishable Key',
        'sk_test_' => 'Stripe Test Secret Key',
        'whsec_' => 'Stripe Webhook Secret',

        // AWS
        'AKIA[0-9A-Z]{16}' => 'AWS Access Key ID',

        // GitHub
        'ghp_[a-zA-Z0-9]{36}' => 'GitHub Personal Access Token',
        'gho_[a-zA-Z0-9]{36}' => 'GitHub OAuth Access Token',
        'github_pat_[a-zA-Z0-9]{22,}' => 'GitHub Fine-Grained Token',

        // 互物通 License Key
        'HWT-[A-Z0-9]{4,}-[A-Z0-9]{4,}-[A-Z0-9]{4,}' => '互物通 License Key',

        // 通用
        '-----BEGIN RSA PRIVATE KEY-----' => 'RSA Private Key',
        '-----BEGIN OPENSSH PRIVATE KEY-----' => 'OpenSSH Private Key',
        '-----BEGIN EC PRIVATE KEY-----' => 'EC Private Key',
        'xox[bpsa]-[0-9]{12}-[0-9]{12}' => 'Slack Token',
        'SLACK_BOT_TOKEN|xapp-[0-9]-[A-Za-z0-9]{10,}' => 'Slack Bot Token',

        // JWT / API Key
        'eyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}' => 'JWT Token',

        // 密码/密钥赋值（宽松检测）
        "'password'\\s*=>\\s*'[^']{6,}'" => 'Hardcoded Password',
        "'secret'\\s*=>\\s*'[^']{6,}'" => 'Hardcoded Secret',
        "'api_key'\\s*=>\\s*'[^']{6,}'" => 'Hardcoded API Key',
    ],

    /*
    |--------------------------------------------------------------------------
    | 排除目录/文件
    |--------------------------------------------------------------------------
    */
    'exclude_paths' => [
        'vendor',
        'node_modules',
        'storage',
        '.git',
        'bootstrap/cache',
        'public/build',
    ],

    /*
    |--------------------------------------------------------------------------
    | 泄露告警
    |--------------------------------------------------------------------------
    |
    | 检测到泄露时通知管理员。
    |
    */
    'alert' => [
        'enabled' => true,
        'channels' => ['mail', 'notification_center'],
        'notify_roles' => ['super-admin', 'admin'],
        'rate_limit_minutes' => 60, // 同一类型告警最小间隔
    ],

    /*
    |--------------------------------------------------------------------------
    | 自动处置策略
    |--------------------------------------------------------------------------
    |
    | auto_revoke: 检测到泄露后自动吊销相关密钥
    | auto_rotate: 检测到泄露后自动轮换密钥
    |
    */
    'remediation' => [
        'auto_revoke' => env('SECRET_AUTO_REVOKE', true),
        'auto_rotate' => env('SECRET_AUTO_ROTATE', false),
        'require_confirmation' => env('SECRET_REQUIRE_CONFIRMATION', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | 扫描调度
    |--------------------------------------------------------------------------
    */
    'schedule' => [
        'full_scan' => env('SECRET_SCAN_SCHEDULE', '0 2 * * 0'), // 每周日凌晨2点
        'quick_scan' => env('SECRET_QUICK_SCAN_SCHEDULE', '0 */6 * * *'), // 每6小时
    ],
];
