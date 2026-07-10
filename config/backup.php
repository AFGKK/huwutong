<?php

return [
    /*
    |--------------------------------------------------------------------------
    | 自动备份配置
    |--------------------------------------------------------------------------
    |
    | 控制数据库和文件备份的行为、保留策略、存储磁盘。
    */

    // 默认存储磁盘
    'disk' => env('BACKUP_DISK', 'local'),

    // S3 兼容对象存储（远程备份用）
    'remote_disk' => env('BACKUP_REMOTE_DISK', 's3'),

    // ─── 数据库备份 ───

    'database' => [
        'enabled' => env('BACKUP_DATABASE_ENABLED', true),

        // MySQL 导出命令路径
        'mysqldump_path' => env('MYSQLDUMP_PATH', 'mysqldump'),

        // PostgreSQL 导出/恢复命令路径
        'pg_dump_path' => env('PG_DUMP_PATH', 'pg_dump'),
        'psql_path' => env('PSQL_PATH', 'psql'),

        // 保留天数
        'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 30),

        // 备份时间表（cron 表达式）
        'schedule' => env('BACKUP_DATABASE_SCHEDULE', '0 2 * * *'), // 每天凌晨2点

        // 压缩等级（0-9，0=不压缩）
        'compression_level' => (int) env('BACKUP_COMPRESSION_LEVEL', 6),

        // 排除的表
        'exclude_tables' => explode(',', env('BACKUP_EXCLUDE_TABLES', '')),
    ],

    // ─── 文件备份 ───

    'files' => [
        'enabled' => env('BACKUP_FILES_ENABLED', false),

        // 保留天数
        'retention_days' => (int) env('BACKUP_FILE_RETENTION_DAYS', 14),

        // 备份时间表
        'schedule' => env('BACKUP_FILES_SCHEDULE', '0 3 * * 0'), // 每周日凌晨3点

        // 包含的目录（相对于项目根目录）
        'include_paths' => [
            'storage/app',
            'storage/logs',
            'public/uploads',
        ],

        // 排除的路径（glob 模式）
        'exclude_patterns' => [
            'storage/logs/laravel-*.log',
            'storage/app/backups/*',
            '*.tmp',
            '.git',
        ],

        // 最大备份大小（MB），0=不限
        'max_size_mb' => (int) env('BACKUP_FILE_MAX_SIZE_MB', 500),
    ],

    // ─── 清理策略 ───

    'cleanup' => [
        // 每次备份后自动清理过期备份
        'auto_cleanup' => env('BACKUP_AUTO_CLEANUP', true),

        // 保留的最近备份数（即使过期也保留）
        'keep_recent' => (int) env('BACKUP_KEEP_RECENT', 5),
    ],

    // ═══════════════════════════════════════
    //  M2-72 云备份增强配置
    // ═══════════════════════════════════════

    /*
    |--------------------------------------------------------------------------
    | 云存储加密
    |--------------------------------------------------------------------------
    |
    | 备份文件上传前使用 AES-256-GCM 加密，密钥存储在本地。
    |
    */
    'cloud_encryption' => [
        'enabled' => env('BACKUP_CLOUD_ENCRYPTION', true),
        // 加密算法
        'cipher' => 'aes-256-gcm',
        // 加密密钥（留空则自动生成）
        'key' => env('BACKUP_ENCRYPTION_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | 多区域冗余
    |--------------------------------------------------------------------------
    |
    | 备份文件自动复制到多个区域/存储提供商。
    |
    */
    'multi_region' => [
        'enabled' => env('BACKUP_MULTI_REGION', false),
        // 额外存储磁盘列表（逗号分隔）
        'replica_disks' => explode(',', env('BACKUP_REPLICA_DISKS', '')),
        // 是否等待副本上传完成
        'wait_for_replicas' => env('BACKUP_WAIT_FOR_REPLICAS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | 备份保留策略（生命周期）
    |--------------------------------------------------------------------------
    |
    | 不同周期的备份保留不同时长，节省存储成本。
    |
    */
    'retention_policy' => [
        // 每日备份保留天数
        'daily' => (int) env('BACKUP_RETENTION_DAILY', 30),
        // 每周备份保留周数
        'weekly' => (int) env('BACKUP_RETENTION_WEEKLY', 12),
        // 每月备份保留月数
        'monthly' => (int) env('BACKUP_RETENTION_MONTHLY', 12),
        // 每年备份保留年数
        'yearly' => (int) env('BACKUP_RETENTION_YEARLY', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | 备份通知
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        'enabled' => env('BACKUP_NOTIFICATIONS', true),
        // 备份成功通知
        'on_success' => env('BACKUP_NOTIFY_SUCCESS', false),
        // 备份失败通知
        'on_failure' => env('BACKUP_NOTIFY_FAILURE', true),
        // 通知渠道
        'channels' => ['mail', 'notification_center'],
    ],

    /*
    |--------------------------------------------------------------------------
    | 备份监控
    |--------------------------------------------------------------------------
    */
    'monitoring' => [
        // 备份超时告警（分钟）
        'timeout_warning_minutes' => (int) env('BACKUP_TIMEOUT_WARNING', 30),
        // 备份文件大小异常检测（与上次偏差超过此百分比告警）
        'size_variance_warning_percent' => (int) env('BACKUP_SIZE_VARIANCE_WARNING', 50),
    ],
];
