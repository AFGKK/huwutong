<?php

return [
    /*
    |--------------------------------------------------------------------------
    | GDPR 合规配置
    |--------------------------------------------------------------------------
    |
    | 数据控制者信息和 GDPR 合规相关配置
    |
    */

    // 数据控制者信息
    'controller' => [
        'name' => env('GDPR_CONTROLLER_NAME', env('APP_NAME')),
        'email' => env('GDPR_CONTROLLER_EMAIL', env('MAIL_FROM_ADDRESS', 'privacy@88.huwutong.com')),
        'address' => env('GDPR_CONTROLLER_ADDRESS', ''),
    ],

    // DSR 请求导出文件过期天数
    'export_expiry_days' => (int) env('GDPR_EXPORT_EXPIRY_DAYS', 30),

    // 数据保留年限
    'retention' => [
        'audit_logs' => (int) env('GDPR_RETENTION_AUDIT', 365),
        'user_data' => (int) env('GDPR_RETENTION_USER', 365 * 3),
        'deleted_accounts' => (int) env('GDPR_RETENTION_DELETED', 90),
    ],
];
