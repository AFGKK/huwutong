<?php

/**
 * 数据本地化存储配置 (M3-60)
 *
 * 按租户/区域指定数据存储位置，自动路由 + 合规审计
 */
return [

    'enabled' => env('DATA_RESIDENCY_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | 区域定义
    |--------------------------------------------------------------------------
    */
    'regions' => [
        'us-east' => [
            'name' => '美东 (弗吉尼亚)',
            'provider' => 'AWS',
            'storage' => 's3',
            'bucket' => 'hwt-us-east',
            'cdn_domain' => 'cdn-us.huwutong.com',
            'compliance' => ['SOC2', 'ISO27001'],
            'latency_ms' => 10,
            'default' => true,
        ],
        'eu-central' => [
            'name' => '欧洲 (法兰克福)',
            'provider' => 'AWS',
            'storage' => 's3-eu',
            'bucket' => 'hwt-eu-central',
            'cdn_domain' => 'cdn-eu.huwutong.com',
            'compliance' => ['GDPR', 'SOC2', 'ISO27001'],
            'latency_ms' => 15,
        ],
        'cn-shanghai' => [
            'name' => '中国 (上海)',
            'provider' => '阿里云',
            'storage' => 'oss',
            'bucket' => 'hwt-cn-shanghai',
            'cdn_domain' => 'cdn-cn.huwutong.com',
            'compliance' => ['PIPL', '等保三级'],
            'latency_ms' => 20,
        ],
        'ap-southeast' => [
            'name' => '新加坡',
            'provider' => 'AWS',
            'storage' => 's3-sg',
            'bucket' => 'hwt-ap-southeast',
            'cdn_domain' => 'cdn-sg.huwutong.com',
            'compliance' => ['ISO27001', 'PDPA'],
            'latency_ms' => 25,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 数据分类
    |--------------------------------------------------------------------------
    */
    'data_classifications' => [
        'customer_pii' => ['region' => 'eu-central', 'encrypt' => true, 'retention_days' => 365],
        'license_keys' => ['region' => 'us-east', 'encrypt' => true, 'retention_days' => 730],
        'audit_logs' => ['region' => 'eu-central', 'encrypt' => false, 'retention_days' => 2555],
        'backups' => ['region' => 'us-east', 'encrypt' => true, 'retention_days' => 90],
        'public_assets' => ['region' => 'us-east', 'encrypt' => false, 'retention_days' => null],
    ],

    /*
    |--------------------------------------------------------------------------
    | 自动路由规则
    |--------------------------------------------------------------------------
    */
    'auto_routing' => [
        'enabled' => env('DATA_RESIDENCY_AUTO_ROUTING', true),
        'fallback_region' => 'us-east',
        'tenant_region_priority' => true, // 先按租户设置，再按数据分类
    ],

    /*
    |--------------------------------------------------------------------------
    | 迁移
    |--------------------------------------------------------------------------
    */
    'migration' => [
        'batch_size' => 100,
        'timeout_minutes' => 60,
        'notify_on_complete' => true,
    ],
];
