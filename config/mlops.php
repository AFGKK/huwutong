<?php

// M3-40 AI MLOps平台配置

return [
    'models' => [
        'storage_disk' => env('ML_MODEL_DISK', 'local'),
        'max_file_size_mb' => 500,
        'allowed_frameworks' => ['tensorflow', 'pytorch', 'onnx', 'sklearn', 'xgboost'],
        'max_versions_per_model' => 50,
        'auto_archive_versions' => true,
    ],

    'training' => [
        'max_concurrent_jobs' => 3,
        'default_epochs' => 100,
        'default_batch_size' => 32,
        'early_stopping_patience' => 10,
        'train_test_split' => 0.2,
        'validation_split' => 0.1,
    ],

    'monitoring' => [
        'drift' => [
            'enabled' => true,
            'check_interval_minutes' => 60,
            'metrics' => ['accuracy', 'precision', 'recall', 'f1', 'latency_p95'],
            'drift_threshold' => 0.1,
            'min_samples' => 100,
        ],
        'performance' => [
            'track_latency' => true,
            'track_memory' => true,
            'track_throughput' => true,
            'alert_on_degradation' => true,
        ],
    ],

    'auto_retrain' => [
        'enabled' => env('ML_AUTO_RETRAIN_ENABLED', false),
        'trigger_on_drift' => true,
        'trigger_on_schedule' => 'weekly',
        'trigger_on_data_accumulation' => 10000,
        'max_retrain_per_day' => 2,
        'notify_on_completion' => true,
    ],

    'deployment' => [
        'strategy' => 'rolling', // rolling|blue_green|canary
        'canary_traffic_percent' => 10,
        'auto_rollback_on_failure' => true,
        'rollback_threshold' => 0.05,
        'health_check_timeout_seconds' => 30,
    ],
];
