<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // ─── ACME / Let's Encrypt ───
    'acme' => [
        'email' => env('ACME_EMAIL', 'admin@huwutong.com'),
        'staging' => env('ACME_STAGING', true), // 生产时设为 false
        'fallback' => env('ACME_FALLBACK', true), // ACME 失败时降级到模拟证书
    ],

    // ─── 自定义域名 CNAME 目标 ───
    'cname_target' => env('CNAME_TARGET', 'cname.huwutong.com.'),

    // ─── 语音识别 (ASR) ───
    'openai' => [
        'api_key' => env('OPENAI_API_KEY', ''),
    ],
    'aliyun' => [
        'asr_app_key' => env('ALIYUN_ASR_APP_KEY', ''),
        'access_key_id' => env('ALIYUN_ACCESS_KEY_ID', ''),
        'access_key_secret' => env('ALIYUN_ACCESS_KEY_SECRET', ''),
    ],
    'tencent' => [
        'asr_secret_id' => env('TENCENT_ASR_SECRET_ID', ''),
        'asr_secret_key' => env('TENCENT_ASR_SECRET_KEY', ''),
    ],

];
