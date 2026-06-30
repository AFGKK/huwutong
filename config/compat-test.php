<?php

// M3-31 兼容性测试矩阵配置

return [
    'platforms' => [
        'categories' => ['php', 'mysql', 'redis', 'browser', 'os'],
        'templates' => [
            'php' => [
                ['name' => 'PHP 8.1', 'version' => '8.1', 'label' => 'PHP 8.1'],
                ['name' => 'PHP 8.2', 'version' => '8.2', 'label' => 'PHP 8.2'],
                ['name' => 'PHP 8.3', 'version' => '8.3', 'label' => 'PHP 8.3'],
            ],
            'mysql' => [
                ['name' => 'MySQL 8.0', 'version' => '8.0', 'label' => 'MySQL 8.0'],
                ['name' => 'MySQL 8.4', 'version' => '8.4', 'label' => 'MySQL 8.4'],
            ],
            'redis' => [
                ['name' => 'Redis 6.x', 'version' => '6', 'label' => 'Redis 6.x'],
                ['name' => 'Redis 7.x', 'version' => '7', 'label' => 'Redis 7.x'],
            ],
        ],
        'auto_initialize' => env('COMPAT_AUTO_INIT', true),
    ],

    'suites' => [
        'default_categories' => ['smoke', 'integration', 'regression', 'security'],
        'max_cases_per_suite' => 200,
    ],

    'runs' => [
        'max_concurrent' => 5,
        'timeout_minutes' => 30,
        'auto_archive_days' => 90,
        'retention_days' => 365,
    ],

    'matrix' => [
        'result_colors' => [
            'passed' => '#67C23A',
            'failed' => '#F56C6C',
            'skipped' => '#909399',
            'pending' => '#E6A23C',
            'running' => '#409EFF',
        ],
        'pass_threshold_percent' => 100,
    ],

    'notifications' => [
        'on_failure' => true,
        'on_regression' => true,
        'channels' => ['mail', 'slack'],
    ],
];
