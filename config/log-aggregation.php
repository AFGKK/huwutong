<?php

// M2-117 集中式日志平台配置

return [
    /*
    |--------------------------------------------------------------------------
    | 存储驱动
    |--------------------------------------------------------------------------
    | database: 存储到 MySQL（中小规模）
    | elasticsearch: 对接 Elasticsearch 集群
    | loki: 对接 Grafana Loki
    */
    'driver' => env('LOG_AGGREGATION_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Elasticsearch 配置
    |--------------------------------------------------------------------------
    */
    'elasticsearch' => [
        'hosts' => [env('LOG_ES_HOST', 'localhost:9200')],
        'index_prefix' => env('LOG_ES_INDEX_PREFIX', 'hwt_logs_'),
        'number_of_shards' => 3,
        'number_of_replicas' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Loki 配置
    |--------------------------------------------------------------------------
    */
    'loki' => [
        'push_url' => env('LOG_LOKI_PUSH_URL', 'http://localhost:3100/loki/api/v1/push'),
        'query_url' => env('LOG_LOKI_QUERY_URL', 'http://localhost:3100/loki/api/v1/query_range'),
        'tenant_id' => env('LOG_LOKI_TENANT_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | 日志保留策略
    |--------------------------------------------------------------------------
    */
    'retention' => [
        'daily' => 30,       // 天级别日志保留 30 天
        'detailed' => 7,     // 详细日志保留 7 天
        'archive' => 365,    // 归档日志保留 1 年
    ],

    /*
    |--------------------------------------------------------------------------
    | 采集配置
    |--------------------------------------------------------------------------
    */
    'collection' => [
        'enabled' => env('LOG_COLLECTION_ENABLED', true),
        'sample_rate' => (int) env('LOG_SAMPLE_RATE', 100), // 百分比 1-100
        'slow_query_threshold_ms' => 200,
        'exclude_paths' => ['/health', '/health/live', '/health/ready'],
        'exclude_status_codes' => [200, 304],
    ],

    /*
    |--------------------------------------------------------------------------
    | 搜索配置
    |--------------------------------------------------------------------------
    */
    'search' => [
        'max_results' => 1000,
        'default_time_range_hours' => 24,
        'max_time_range_hours' => 168, // 7 天
        'highlight_tags' => ['<mark>', '</mark>'],
    ],

    /*
    |--------------------------------------------------------------------------
    | 告警规则
    |--------------------------------------------------------------------------
    */
    'alerting' => [
        'error_rate_threshold' => 5,   // 5% 错误率触发告警
        'error_count_threshold' => 100, // 100 次/分钟触发告警
        'notification_channels' => ['mail', 'slack'],
    ],
];
