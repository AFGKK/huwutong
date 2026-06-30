<?php

// M3-41 AI 特征工程平台配置

return [
    'feature_store' => [
        'online' => [
            'driver' => env('FEATURE_ONLINE_DRIVER', 'redis'),
            'redis_prefix' => 'feature:',
            'ttl_seconds' => 86400,
            'max_batch_size' => 1000,
        ],
        'offline' => [
            'driver' => env('FEATURE_OFFLINE_DRIVER', 'database'),
            'table_prefix' => 'feature_offline_',
            'retention_days' => 365,
            'partition_by' => 'event_date',
        ],
    ],

    'features' => [
        'max_groups_per_entity' => 50,
        'max_features_per_group' => 200,
        'allowed_types' => ['int', 'float', 'double', 'string', 'boolean', 'json', 'vector'],
        'default_ttl' => 3600,
    ],

    'consistency' => [
        'check_interval_minutes' => 30,
        'max_drift_percent' => 5.0,
        'alert_on_drift' => true,
        'auto_sync' => true,
        'sample_size' => 1000,
    ],

    'sources' => [
        'types' => ['manual', 'sql_query', 'api_endpoint', 'kafka_topic', 'file_upload', 'model_output'],
        'max_query_timeout_seconds' => 30,
    ],
];
