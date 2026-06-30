<?php

/**
 * M2-73 审计日志归档至低成本存储
 *
 * S3 Glacier Deep Archive / B2 等冷存储归档 + 合规长期留存 + 按需取回。
 * 依赖 M1.3-16 云存储统一适配层。
 */
return [

    /*
    |--------------------------------------------------------------------------
    | 存储层级配置
    |--------------------------------------------------------------------------
    */
    'tiers' => [
        'hot' => [
            'label' => '热存储',
            'description' => '高频访问，本地数据库',
            'retention_days' => 90,
            'storage_class' => null,
        ],
        'warm' => [
            'label' => '温存储',
            'description' => '中频访问，标准云存储',
            'retention_days' => 365,
            'storage_class' => 'STANDARD',
            'cost_per_gb_month' => 0.023,
        ],
        'cold' => [
            'label' => '冷存储',
            'description' => '低频访问，Glacier Deep Archive',
            'retention_days' => 2555, // 7年
            'storage_class' => 'DEEP_ARCHIVE',
            'cost_per_gb_month' => 0.001,
            'retrieval_cost_per_gb' => 0.02,
            'retrieval_hours' => 12, // 取回时间12小时内
        ],
        'frozen' => [
            'label' => '冻结存储',
            'description' => '合规长期留存，B2/Glacier',
            'retention_days' => 3650, // 10年
            'storage_class' => 'GLACIER',
            'cost_per_gb_month' => 0.00099,
            'retrieval_cost_per_gb' => 0.01,
            'retrieval_hours' => 48,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 归档策略
    |--------------------------------------------------------------------------
    */
    'strategy' => [
        // 默认归档存储驱动（映射到 cloud-storage 适配器）
        'default_archive_disk' => env('LOG_ARCHIVE_DISK', 's3'),

        // 温存储驱动（标准访问）
        'warm_disk' => env('LOG_ARCHIVE_WARM_DISK', 's3'),

        // 冷存储驱动（低成本）
        'cold_disk' => env('LOG_ARCHIVE_COLD_DISK', 's3'),

        // 归档路径前缀
        'path_prefix' => env('LOG_ARCHIVE_PATH_PREFIX', 'archives/audit'),

        // 加密归档文件
        'encrypt_archive' => env('LOG_ARCHIVE_ENCRYPT', true),

        // 压缩归档文件（gzip）
        'compress' => env('LOG_ARCHIVE_COMPRESS', true),

        // 分块大小（条数）
        'chunk_size' => env('LOG_ARCHIVE_CHUNK_SIZE', 500),
    ],

    /*
    |--------------------------------------------------------------------------
    | 按需取回配置
    |--------------------------------------------------------------------------
    */
    'retrieval' => [
        // 取回请求有效期（天）
        'request_expires_days' => env('LOG_ARCHIVE_REQUEST_EXPIRES', 7),

        // 取回后临时存储天数
        'temp_storage_days' => env('LOG_ARCHIVE_TEMP_STORAGE_DAYS', 3),

        // 最大并发取回请求数
        'max_concurrent_requests' => env('LOG_ARCHIVE_MAX_CONCURRENT', 5),

        // 取回通知渠道
        'notify_channels' => ['mail', 'notification'],
    ],

    /*
    |--------------------------------------------------------------------------
    | 合规配置
    |--------------------------------------------------------------------------
    */
    'compliance' => [
        // 法规要求的最短保留年限
        'minimum_retention_years' => env('LOG_ARCHIVE_MIN_RETENTION_YEARS', 3),

        // 是否启用 WORM（一次写入多次读取）
        'worm_enabled' => env('LOG_ARCHIVE_WORM_ENABLED', false),

        // 归档审计日志保留（年）
        'audit_log_retention_years' => env('LOG_ARCHIVE_AUDIT_RETENTION_YEARS', 7),
    ],
];
