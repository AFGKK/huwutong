<?php

/**
 * 数据留存策略配置 (M1.1-14)
 *
 * 集中管理全系统数据留存策略与审计日志归档方案。
 * 各模块的留存配置应引用此处的全局策略。
 */

return [

    /*
    |--------------------------------------------------------------------------
    | 全局留存策略
    |--------------------------------------------------------------------------
    | 按数据分类定义统一的保留天数。
    | 各类数据的清理可独立开关。
    */
    'policies' => [

        // ─── 审计日志 ───
        'audit_logs' => [
            'retention_days' => env('RETENTION_AUDIT_LOGS', 365),      // 1年
            'archive_enabled' => env('RETENTION_AUDIT_ARCHIVE', true),  // 归档启用
            'archive_after_days' => env('RETENTION_AUDIT_ARCHIVE_AFTER', 90), // 90天后归档
            'cold_storage_days' => env('RETENTION_AUDIT_COLD', 365),   // 1年后转冷存储
            'action' => env('RETENTION_AUDIT_ACTION', 'archive'),      // archive | delete | anonymize
        ],

        // ─── 活动日志 ───
        'activity_logs' => [
            'retention_days' => env('RETENTION_ACTIVITY_LOGS', 90),
            'action' => env('RETENTION_ACTIVITY_ACTION', 'delete'),
        ],

        // ─── 激活记录 ───
        'activation_logs' => [
            'retention_days' => env('RETENTION_ACTIVATION', 365 * 2),   // 2年
            'action' => env('RETENTION_ACTIVATION_ACTION', 'archive'),
        ],

        // ─── Webhook 事件 ───
        'webhook_events' => [
            'retention_days' => env('RETENTION_WEBHOOK', 30),
            'action' => env('RETENTION_WEBHOOK_ACTION', 'delete'),
        ],

        // ─── Webhook 回放记录 ───
        'webhook_replays' => [
            'retention_days' => env('RETENTION_WEBHOOK_REPLAY', 90),
            'action' => env('RETENTION_WEBHOOK_REPLAY_ACTION', 'delete'),
        ],

        // ─── WAF 攻击日志 ───
        'waf_logs' => [
            'retention_days' => env('RETENTION_WAF', 30),
            'action' => env('RETENTION_WAF_ACTION', 'delete'),
        ],

        // ─── WAF 统计数据 ───
        'waf_stats' => [
            'retention_days' => env('RETENTION_WAF_STATS', 365),
            'action' => env('RETENTION_WAF_STATS_ACTION', 'delete'),
        ],

        // ─── gRPC 调用日志 ───
        'grpc_logs' => [
            'retention_days' => env('RETENTION_GRPC', 30),
            'action' => env('RETENTION_GRPC_ACTION', 'delete'),
        ],

        // ─── 通知记录 ───
        'notification_logs' => [
            'retention_days' => env('RETENTION_NOTIFICATION', 90),
            'action' => env('RETENTION_NOTIFICATION_ACTION', 'delete'),
        ],

        // ─── 邮件发送记录 ───
        'email_logs' => [
            'retention_days' => env('RETENTION_EMAIL', 180),
            'action' => env('RETENTION_EMAIL_ACTION', 'delete'),
        ],

        // ─── 设备心跳记录 ───
        'device_heartbeats' => [
            'retention_days' => env('RETENTION_HEARTBEAT', 30),
            'action' => env('RETENTION_HEARTBEAT_ACTION', 'delete'),
        ],

        // ─── API 调用统计 ───
        'api_stats' => [
            'retention_days' => env('RETENTION_API_STATS', 365),
            'action' => env('RETENTION_API_STATS_ACTION', 'delete'),
        ],

        // ─── 队列失败任务 ───
        'failed_jobs' => [
            'retention_days' => env('RETENTION_FAILED_JOBS', 14),
            'action' => env('RETENTION_FAILED_JOBS_ACTION', 'delete'),
        ],

        // ─── 告警记录 ───
        'alert_logs' => [
            'retention_days' => env('RETENTION_ALERTS', 90),
            'action' => env('RETENTION_ALERTS_ACTION', 'delete'),
        ],

        // ─── 慢查询日志 ───
        'slow_query_logs' => [
            'retention_days' => env('RETENTION_SLOW_QUERY', 30),
            'action' => env('RETENTION_SLOW_QUERY_ACTION', 'delete'),
        ],

        // ─── 客户审计日志 ───
        'customer_audit_logs' => [
            'retention_days' => env('RETENTION_CUSTOMER_AUDIT', 90),
            'action' => env('RETENTION_CUSTOMER_AUDIT_ACTION', 'delete'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 日志归档配置
    |--------------------------------------------------------------------------
    */
    'archive' => [
        'enabled' => env('RETENTION_ARCHIVE_ENABLED', true),
        'disk' => env('RETENTION_ARCHIVE_DISK', 's3'),          // 归档存储磁盘
        'path' => env('RETENTION_ARCHIVE_PATH', 'archives/'),
        'encrypt' => env('RETENTION_ARCHIVE_ENCRYPT', true),    // 归档文件加密
        'compress' => env('RETENTION_ARCHIVE_COMPRESS', true),  // 归档文件压缩
        'storage_tiers' => [
            'hot' => ['label' => '热存储(SSD)', 'cost_per_gb' => 0.10, 'retrieval_time' => '即时'],
            'warm' => ['label' => '温存储(HDD)', 'cost_per_gb' => 0.03, 'retrieval_time' => '即时'],
            'cold' => ['label' => '冷存储(S3 Standard-IA)', 'cost_per_gb' => 0.01, 'retrieval_time' => '即时'],
            'frozen' => ['label' => '冻结存储(S3 Glacier)', 'cost_per_gb' => 0.004, 'retrieval_time' => '5-12小时'],
            'deep_frozen' => ['label' => '深度冻结(Glacier Deep Archive)', 'cost_per_gb' => 0.001, 'retrieval_time' => '12-48小时'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 数据匿名化保留策略
    |--------------------------------------------------------------------------
    | 账号注销后，匿名化数据可保留用于统计分析的时长。
    */
    'anonymization' => [
        'retention_days' => env('RETENTION_ANONYMIZED', 365 * 3),  // 3年
        'delete_after_days' => env('RETENTION_ANONYMIZED_DELETE', 365 * 7), // 7年后彻底删除
    ],

    /*
    |--------------------------------------------------------------------------
    | 清理执行配置
    |--------------------------------------------------------------------------
    */
    'cleanup' => [
        'batch_size' => env('RETENTION_BATCH_SIZE', 5000),      // 每批处理记录数
        'pause_between_batches' => env('RETENTION_BATCH_PAUSE', 100), // 批次间隔(ms)
        'max_execution_time' => env('RETENTION_MAX_TIME', 300), // 单次执行最大时间(秒)
        'dry_run_default' => env('RETENTION_DRY_RUN', false),   // 默认预览模式
    ],

    /*
    |--------------------------------------------------------------------------
    | 数据分类标签
    |--------------------------------------------------------------------------
    */
    'categories' => [
        'audit' => ['label' => '审计日志', 'icon' => 'Clipboard', 'color' => '#409eff'],
        'security' => ['label' => '安全日志', 'icon' => 'WarningFilled', 'color' => '#e6a23c'],
        'operation' => ['label' => '操作日志', 'icon' => 'Setting', 'color' => '#67c23a'],
        'notification' => ['label' => '通知记录', 'icon' => 'Bell', 'color' => '#909399'],
        'performance' => ['label' => '性能数据', 'icon' => 'DataAnalysis', 'color' => '#f56c6c'],
    ],
];
