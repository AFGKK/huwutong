<?php

// 防盗版暗水印机制配置 (M3-10)

return [

    /*
    |--------------------------------------------------------------------------
    | 暗水印算法配置
    |--------------------------------------------------------------------------
    */
    'algorithms' => [
        'stealth' => [
            'name' => 'Stealth',
            'description' => '基础隐写水印',
            'key_length' => 42,
        ],
        'hmac' => [
            'name' => 'HMAC-SHA256',
            'description' => 'HMAC签名水印',
            'key_length' => 42,
        ],
        'bloom' => [
            'name' => 'Bloom Filter',
            'description' => '布隆过滤器水印(空间高效)',
            'key_length' => 42,
        ],
        'forensic_stealth' => [
            'name' => 'Forensic Stealth',
            'description' => '隐写式暗水印(加密载荷+设备指纹+GeoIP)',
            'key_length' => 42,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 嵌入位置
    |--------------------------------------------------------------------------
    */
    'embed_locations' => [
        'metadata' => 'License Metadata',
        'license_key' => 'License Key 结构',
        'integrity_hash' => '完整性哈希',
        'sdk_response' => 'SDK 响应头',
        'certificate' => '证书字段',
    ],

    /*
    |--------------------------------------------------------------------------
    | 溯源码配置
    |--------------------------------------------------------------------------
    */
    'forensic' => [
        // 载荷字段
        'payload_fields' => [
            'license_key_hash',
            'customer_id',
            'customer_name_hash',
            'tenant_id',
            'device_fingerprint',
            'ip_address',
            'geoip',
            'embedded_at',
        ],

        // 签名算法
        'signature_algorithm' => 'hmac-sha256',

        // 嵌入类型
        'embed_types' => [
            'metadata',
            'license_key',
            'integrity_hash',
            'sdk_response',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 泄漏扫描
    |--------------------------------------------------------------------------
    */
    'leak_scan' => [
        'enabled' => env('WATERMARK_LEAK_SCAN_ENABLED', false),
        'scan_interval_minutes' => 1440, // 每天一次
        'max_urls_per_scan' => 100,
        'sources' => [
            'github' => [
                'enabled' => true,
                'search_query' => 'huwutong license key',
            ],
            'pastebin' => [
                'enabled' => false,
            ],
            'darkweb' => [
                'enabled' => false,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 验证日志
    |--------------------------------------------------------------------------
    */
    'verification' => [
        'log_retention_days' => 90,
        'max_logs_per_license' => 1000,
        'alert_on_consecutive_failures' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | 批量操作
    |--------------------------------------------------------------------------
    */
    'batch' => [
        'max_licenses_per_batch' => 100,
        'chunk_size' => 20,
    ],
];
