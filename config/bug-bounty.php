<?php

/**
 * Bug Bounty 安全漏洞披露计划配置 (M3-75)
 */
return [
    /*
    |--------------------------------------------------------------------------
    | 程序名称
    |--------------------------------------------------------------------------
    */
    'program_name' => '互物通 (HuWuTong) Bug Bounty Program',

    /*
    |--------------------------------------------------------------------------
    | 联系信息
    |--------------------------------------------------------------------------
    */
    'contact' => [
        'email' => 'security@huwutong.com',
        'pgp_fingerprint' => 'F3FA E9A7 2B8D 1C4E 9A0B 5C6D 7E8F 9A0B 1C2D 3E4F',
        'response_time' => '48小时内确认接收，5个工作日内初步评估',
    ],

    /*
    |--------------------------------------------------------------------------
    | HackerOne 集成
    |--------------------------------------------------------------------------
    */
    'hackerone' => [
        'enabled' => env('HACKERONE_ENABLED', false),
        'api_url' => env('HACKERONE_API_URL', 'https://api.hackerone.com/v1'),
        'api_key' => env('HACKERONE_API_KEY'),
        'program_handle' => env('HACKERONE_PROGRAM_HANDLE', 'huwutong'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Bugcrowd 集成
    |--------------------------------------------------------------------------
    */
    'bugcrowd' => [
        'enabled' => env('BUGCROWD_ENABLED', false),
        'api_url' => env('BUGCROWD_API_URL', 'https://api.bugcrowd.com/v1'),
        'api_key' => env('BUGCROWD_API_KEY'),
        'program_code' => env('BUGCROWD_PROGRAM_CODE', 'huwutong'),
    ],

    /*
    |--------------------------------------------------------------------------
    | 赏金 (USD)
    |--------------------------------------------------------------------------
    */
    'rewards' => [
        'critical' => ['min' => 500, 'max' => 2000],
        'high' => ['min' => 200, 'max' => 500],
        'medium' => ['min' => 100, 'max' => 200],
        'low' => ['min' => 50, 'max' => 100],
        'informational' => ['min' => 0, 'max' => 0],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security.txt (RFC 9116)
    |--------------------------------------------------------------------------
    */
    'security_txt' => [
        'enabled' => true,
        'expires_days' => 180,
        'canonical_url' => env('APP_URL') . '/.well-known/security.txt',
    ],

    /*
    |--------------------------------------------------------------------------
    | 响应时间
    |--------------------------------------------------------------------------
    */
    'response' => [
        'acknowledge_hours' => 48,
        'assessment_days' => 5,
        'fix_grace_days' => 90,
        'disclosure_delay_days' => 30,
    ],
];
