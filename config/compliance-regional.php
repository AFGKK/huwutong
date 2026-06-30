<?php

// M3-18 多区域合规配置

return [
    'regions' => [
        'cn' => [
            'name' => '中国大陆',
            'code' => 'CN',
            'currency' => 'CNY',
            'timezone' => 'Asia/Shanghai',
            'languages' => ['zh-CN'],
            'compliance' => [
                'gdpr' => false,
                'pipl' => true,
                'vat' => false,
                'data_residency' => true,
                'cookie_consent' => true,
                'tax_reporting' => true,
            ],
            'tax' => [
                'type' => 'vat',
                'rate' => 13,
                'reporting_frequency' => 'monthly',
                'digital_service_tax' => false,
            ],
            'sales_restrictions' => [
                'requires_icp' => true,
                'export_controlled' => false,
                'allowed_industries' => ['*'],
            ],
        ],
        'eu' => [
            'name' => '欧盟',
            'code' => 'EU',
            'currency' => 'EUR',
            'timezone' => 'Europe/Berlin',
            'languages' => ['en', 'de', 'fr', 'es', 'it'],
            'compliance' => [
                'gdpr' => true,
                'pipl' => false,
                'vat' => true,
                'data_residency' => false,
                'cookie_consent' => true,
                'tax_reporting' => true,
            ],
            'tax' => [
                'type' => 'vat',
                'rate' => 20,
                'reporting_frequency' => 'quarterly',
                'digital_service_tax' => true,
                'oss_enabled' => true,
                'oss_threshold' => 10000,
            ],
            'sales_restrictions' => [
                'requires_icp' => false,
                'export_controlled' => true,
                'allowed_industries' => ['*'],
                'restricted_countries' => ['BY', 'RU', 'IR', 'KP'],
            ],
        ],
        'us' => [
            'name' => '美国',
            'code' => 'US',
            'currency' => 'USD',
            'timezone' => 'America/New_York',
            'languages' => ['en'],
            'compliance' => [
                'gdpr' => false,
                'pipl' => false,
                'vat' => false,
                'data_residency' => false,
                'cookie_consent' => true,
                'tax_reporting' => true,
            ],
            'tax' => [
                'type' => 'sales_tax',
                'rate' => 0, // 各州不同
                'reporting_frequency' => 'monthly',
                'digital_service_tax' => false,
            ],
            'sales_restrictions' => [
                'requires_icp' => false,
                'export_controlled' => true,
                'allowed_industries' => ['*'],
                'restricted_countries' => ['IR', 'KP', 'SY', 'CU'],
            ],
        ],
        'ap-southeast' => [
            'name' => '东南亚',
            'code' => 'APAC',
            'currency' => 'SGD',
            'timezone' => 'Asia/Singapore',
            'languages' => ['en', 'zh-CN', 'ja', 'ko'],
            'compliance' => [
                'gdpr' => false,
                'pipl' => false,
                'vat' => true,
                'data_residency' => false,
                'cookie_consent' => true,
                'tax_reporting' => true,
            ],
            'tax' => [
                'type' => 'gst',
                'rate' => 9,
                'reporting_frequency' => 'quarterly',
                'digital_service_tax' => true,
            ],
            'sales_restrictions' => [
                'requires_icp' => false,
                'export_controlled' => false,
                'allowed_industries' => ['*'],
            ],
        ],
    ],

    'default_region' => 'cn',

    'auto_detect_region' => [
        'enabled' => true,
        'ip_header' => 'X-Geo-Country',
        'geoip_db' => storage_path('app/geoip/GeoLite2-Country.mmdb'),
    ],

    'reporting' => [
        'retention_days' => 365,
        'auto_generate' => true,
        'auto_generate_day' => 1,
        'notification_channels' => ['email', 'notification'],
    ],

    'dashboard' => [
        'refresh_interval_seconds' => 300,
    ],
];
