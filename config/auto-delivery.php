<?php

// M2-147 🛒 多渠道送达系统配置

return [

    /*
    |--------------------------------------------------------------------------
    | 送达渠道
    |--------------------------------------------------------------------------
    */
    'channels' => [
        'page' => [
            'enabled' => true,
            'label' => '页面即时展示',
        ],
        'email' => [
            'enabled' => env('DELIVERY_EMAIL_ENABLED', true),
            'label' => '邮件发送',
            // 邮件模板 ID（关联邮件模板管理）
            'template_id' => env('DELIVERY_EMAIL_TEMPLATE', 'delivery_success'),
        ],
        'webhook' => [
            'enabled' => env('DELIVERY_WEBHOOK_ENABLED', true),
            'label' => 'Webhook 推送',
            // 超时（秒）
            'timeout' => 10,
            // 重试次数
            'retries' => 3,
        ],
        'api_callback' => [
            'enabled' => env('DELIVERY_API_CALLBACK_ENABLED', false),
            'label' => 'API 回调',
            'timeout' => 15,
            'retries' => 3,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 重试策略
    |--------------------------------------------------------------------------
    */
    'retry' => [
        // 最大重试次数
        'max_attempts' => 5,
        // 重试间隔（秒），支持数组实现指数退避
        'intervals' => [60, 300, 900, 3600, 14400],
        // 失败后自动重试的渠道
        'auto_retry_channels' => ['email', 'webhook', 'api_callback'],
    ],

    /*
    |--------------------------------------------------------------------------
    | 交付物类型
    |--------------------------------------------------------------------------
    */
    'delivery_types' => [
        'license_key' => ['label' => 'License Key', 'icon' => 'Key'],
        'activation_code' => ['label' => '激活码', 'icon' => 'Link'],
        'download_link' => ['label' => '下载链接', 'icon' => 'Download'],
        'api_key' => ['label' => 'API Key', 'icon' => 'Key'],
        'file_package' => ['label' => '文件包', 'icon' => 'Folder'],
    ],

    /*
    |--------------------------------------------------------------------------
    | 日志
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'channel' => env('DELIVERY_LOG_CHANNEL', 'stack'),
        'level' => env('DELIVERY_LOG_LEVEL', 'info'),
        // 送达日志保留天数
        'retention_days' => 90,
    ],

];
