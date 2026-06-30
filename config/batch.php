<?php

// M2-08 批量操作工具 + 撤销/回滚配置

return [
    'execution' => [
        'max_items_per_batch' => env('BATCH_MAX_ITEMS', 5000),
        'max_concurrent_jobs' => 3,
        'timeout_minutes' => 30,
        'chunk_size' => 100,
        'preview_enabled' => true,
        'preview_max_items' => 200,
    ],

    'undo' => [
        'enabled' => true,
        'window_minutes' => env('BATCH_UNDO_WINDOW', 30),
        'require_confirmation' => true,
    ],

    'export' => [
        'format' => 'csv',
        'max_export_items' => 50000,
        'expiry_hours' => 24,
    ],

    'operation_types' => [
        'update_status' => [
            'label' => '批量更新状态',
            'requires_snapshot' => true,
            'supports_undo' => true,
        ],
        'extend_expiry' => [
            'label' => '批量延期',
            'requires_snapshot' => true,
            'supports_undo' => true,
        ],
        'update_customer' => [
            'label' => '批量变更客户',
            'requires_snapshot' => true,
            'supports_undo' => true,
        ],
        'delete' => [
            'label' => '批量删除',
            'requires_snapshot' => true,
            'supports_undo' => true,
        ],
        'export' => [
            'label' => '批量导出',
            'requires_snapshot' => false,
            'supports_undo' => false,
        ],
    ],
];
