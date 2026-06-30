<?php

// M2-92 IP范围限制 + M2-93 地理围栏 配置

return [
    /*
    |--------------------------------------------------------------------------
    | IP 范围限制
    |--------------------------------------------------------------------------
    */
    'ip_restriction' => [
        'enabled' => env('IP_RESTRICTION_ENABLED', true),
        'max_cidr_entries' => 50,
        'default_action' => 'block', // block / allow / audit
        'support_ipv6' => true,
        'check_on_activate' => true,
        'check_on_validate' => true,
        'cache_ttl_seconds' => 300,
    ],

    /*
    |--------------------------------------------------------------------------
    | 地理围栏
    |--------------------------------------------------------------------------
    */
    'geo_fence' => [
        'enabled' => env('GEO_FENCE_ENABLED', true),
        'default_action' => 'block', // block / allow / audit
        'ip_database' => env('GEO_IP_DATABASE', 'geoip'), // geoip / ip2location / maxmind
        'maxmind_db_path' => env('MAXMIND_DB_PATH', storage_path('app/geoip/GeoLite2-City.mmdb')),
        'allowed_countries' => [],
        'blocked_countries' => [],
        'check_on_activate' => true,
        'check_on_validate' => true,
        'cache_ttl_seconds' => 3600,
        'unknown_location_action' => 'allow', // allow / block / audit
    ],

    /*
    |--------------------------------------------------------------------------
    | 共用配置
    |--------------------------------------------------------------------------
    */
    'common' => [
        'enabled' => true,
        'approval_required' => env('RESTRICTION_APPROVAL_REQUIRED', true),
        'notify_on_block' => true,
        'log_all_checks' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | 国家/地区代码 (ISO 3166-1 alpha-2)
    |--------------------------------------------------------------------------
    */
    'countries' => [
        'CN' => '中国',
        'HK' => '中国香港',
        'TW' => '中国台湾',
        'MO' => '中国澳门',
        'US' => '美国',
        'GB' => '英国',
        'JP' => '日本',
        'KR' => '韩国',
        'DE' => '德国',
        'FR' => '法国',
        'SG' => '新加坡',
        'AU' => '澳大利亚',
        'CA' => '加拿大',
        'IN' => '印度',
        'RU' => '俄罗斯',
        'BR' => '巴西',
        'AE' => '阿联酋',
        'NL' => '荷兰',
        'SE' => '瑞典',
        'CH' => '瑞士',
        'IL' => '以色列',
        'ZA' => '南非',
    ],
];
