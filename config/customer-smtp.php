<?php

// M2-83 + M2-84 客户 SMTP 配置 & 多渠道降级

return [
    /*
    |--------------------------------------------------------------------------
    | 预设 SMTP 提供商的配置模板
    |--------------------------------------------------------------------------
    */
    'providers' => [
        'qq' => [
            'name' => 'QQ邮箱',
            'host' => 'smtp.qq.com',
            'port' => 465,
            'encryption' => 'ssl',
            'auth' => 'login',
        ],
        '163' => [
            'name' => '163邮箱',
            'host' => 'smtp.163.com',
            'port' => 465,
            'encryption' => 'ssl',
            'auth' => 'login',
        ],
        'gmail' => [
            'name' => 'Gmail',
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'encryption' => 'tls',
            'auth' => 'login',
        ],
        'outlook' => [
            'name' => 'Outlook/Hotmail',
            'host' => 'smtp.office365.com',
            'port' => 587,
            'encryption' => 'tls',
            'auth' => 'login',
        ],
        'qq_exmail' => [
            'name' => '企业微信邮箱',
            'host' => 'smtp.exmail.qq.com',
            'port' => 465,
            'encryption' => 'ssl',
            'auth' => 'login',
        ],
        'aliyun' => [
            'name' => '阿里云邮件',
            'host' => 'smtp.aliyun.com',
            'port' => 465,
            'encryption' => 'ssl',
            'auth' => 'login',
        ],
        'custom' => [
            'name' => '自定义 SMTP',
            'host' => '',
            'port' => 587,
            'encryption' => 'tls',
            'auth' => 'login',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 降级配置
    |--------------------------------------------------------------------------
    */
    'fallback' => [
        // 主 SMTP 发送失败多少次后触发降级
        'failure_threshold' => env('SMTP_FAILURE_THRESHOLD', 3),
        // 降级后多久尝试恢复主 SMTP（分钟）
        'recovery_interval' => env('SMTP_RECOVERY_INTERVAL', 30),
        // 告警通知邮箱
        'alert_email' => env('SMTP_ALERT_EMAIL', 'admin@huwutong.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | 系统默认 SMTP（最终兜底）
    |--------------------------------------------------------------------------
    */
    'system_default' => [
        'host' => env('MAIL_HOST', 'mailpit'),
        'port' => env('MAIL_PORT', 1025),
        'encryption' => env('MAIL_ENCRYPTION', null),
        'username' => env('MAIL_USERNAME', null),
        'password' => env('MAIL_PASSWORD', null),
        'from_address' => env('MAIL_FROM_ADDRESS', 'noreply@huwutong.com'),
        'from_name' => env('MAIL_FROM_NAME', '互物通'),
    ],
];
