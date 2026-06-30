<?php

// M3-52 多区域部署配置

return [
    'regions' => [
        'us-east' => [
            'name' => '美东',
            'provider' => 'aws',
            'region_code' => 'us-east-1',
            'api_url' => env('API_URL_US_EAST', 'https://api-us.huwutong.com'),
            'db_host' => env('DB_HOST_US_EAST', ''),
            'redis_host' => env('REDIS_HOST_US_EAST', ''),
            'storage_bucket' => env('STORAGE_BUCKET_US_EAST', ''),
            'is_active' => true,
            'weight' => 40,
            'latency_base_ms' => 5,
        ],
        'eu-west' => [
            'name' => '欧洲',
            'provider' => 'aws',
            'region_code' => 'eu-west-1',
            'api_url' => env('API_URL_EU_WEST', 'https://api-eu.huwutong.com'),
            'db_host' => env('DB_HOST_EU_WEST', ''),
            'redis_host' => env('REDIS_HOST_EU_WEST', ''),
            'storage_bucket' => env('STORAGE_BUCKET_EU_WEST', ''),
            'is_active' => true,
            'weight' => 30,
            'latency_base_ms' => 50,
        ],
        'ap-southeast' => [
            'name' => '东南亚',
            'provider' => 'aws',
            'region_code' => 'ap-southeast-1',
            'api_url' => env('API_URL_AP_SOUTHEAST', 'https://api-ap.huwutong.com'),
            'db_host' => env('DB_HOST_AP_SOUTHEAST', ''),
            'redis_host' => env('REDIS_HOST_AP_SOUTHEAST', ''),
            'storage_bucket' => env('STORAGE_BUCKET_AP_SOUTHEAST', ''),
            'is_active' => true,
            'weight' => 30,
            'latency_base_ms' => 80,
        ],
    ],

    'routing' => [
        'strategy' => 'geo_dns', // geo_dns|latency_based|weighted_random|header_based
        'geoip_db' => storage_path('app/geoip/GeoLite2-City.mmdb'),
        'default_region' => 'us-east',
        'fallback_region' => 'us-east',
        'sticky_session' => true,
        'session_ttl_minutes' => 60,
    ],

    'sync' => [
        'data_types' => ['license', 'customer', 'product', 'audit_log'],
        'sync_interval_seconds' => 60,
        'batch_size' => 100,
        'conflict_resolution' => 'last_write_wins',
        'use_event_bus' => true,
    ],

    'health' => [
        'check_interval_seconds' => 30,
        'unhealthy_threshold' => 3,
        'recovery_threshold' => 2,
        'timeout_seconds' => 5,
    ],

    'dns' => [
        'provider' => env('DNS_PROVIDER', 'route53'),
        'ttl_seconds' => 60,
        'health_check_path' => '/api/health',
        'weight_based_routing' => true,
    ],
];
