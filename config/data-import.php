<?php

// M2-56 CSV 批量导入校验配置

return [
    'file' => [
        'max_size_mb' => env('IMPORT_MAX_SIZE_MB', 10),
        'allowed_mime_types' => ['text/csv', 'text/plain', 'application/vnd.ms-excel'],
        'max_columns' => 50,
        'max_rows' => env('IMPORT_MAX_ROWS', 50000),
        'encoding' => 'UTF-8',
        'delimiter' => ',',
    ],

    'validation' => [
        'max_errors_before_abort' => 100,
        'strict_mode' => env('IMPORT_STRICT_MODE', false),
        'trim_whitespace' => true,
        'skip_empty_rows' => true,
    ],

    'mapping' => [
        'auto_detect_columns' => true,
        'fuzzy_match_threshold' => 0.8,
        'allow_partial_mapping' => true,
    ],

    'task' => [
        'cleanup_after_days' => 30,
        'max_concurrent_tasks' => 3,
        'chunk_size' => 500,
    ],
];
