<?php

// D-04 短信服务配置

return [
    /*
    |--------------------------------------------------------------------------
    | 默认驱动
    |--------------------------------------------------------------------------
    | log: 仅写日志（开发） | aliyun: 阿里云短信
    */
    'driver' => env('SMS_DRIVER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | 生产环境是否允许回退到 log 驱动
    |--------------------------------------------------------------------------
    */
    'fallback_to_log' => env('SMS_FALLBACK_TO_LOG', true),

    /*
    |--------------------------------------------------------------------------
    | 阿里云短信
    |--------------------------------------------------------------------------
    */
    'aliyun' => [
        'access_key_id' => env('ALIYUN_SMS_ACCESS_KEY_ID', ''),
        'access_key_secret' => env('ALIYUN_SMS_ACCESS_KEY_SECRET', ''),
        'sign_name' => env('ALIYUN_SMS_SIGN_NAME', '互物通'),
        'template_code' => env('ALIYUN_SMS_TEMPLATE_CODE', ''),
        'notification_template_code' => env('ALIYUN_SMS_NOTIFICATION_TEMPLATE', ''),
        'region_id' => env('ALIYUN_SMS_REGION_ID', 'cn-hangzhou'),
        'endpoint' => env('ALIYUN_SMS_ENDPOINT', 'https://dysmsapi.aliyuncs.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | 验证码
    |--------------------------------------------------------------------------
    */
    'verification' => [
        'ttl_seconds' => (int) env('SMS_CODE_TTL', 300),
        'rate_limit_seconds' => (int) env('SMS_CODE_RATE_LIMIT', 60),
    ],
];
