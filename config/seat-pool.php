<?php

// M3-45 License 席位池管理配置

return [
    'defaults' => [
        'mode' => env('SEAT_POOL_DEFAULT_MODE', 'shared'), // shared|exclusive|auto
        'timeout_minutes' => env('SEAT_POOL_TIMEOUT', 30),
        'waiting_limit' => env('SEAT_POOL_WAITING_LIMIT', 50),
    ],

    'modes' => [
        'shared' => [
            'label' => '共享模式',
            'description' => 'N个席位共享给所有设备，先到先得',
            'max_seats' => 1000,
        ],
        'exclusive' => [
            'label' => '独占模式',
            'description' => '每个设备独占一个席位，不可共享',
            'max_seats' => 100,
        ],
        'auto' => [
            'label' => '自动排队模式',
            'description' => '有空位就占用，超限自动排队等待',
            'max_seats' => 500,
            'queue_timeout_minutes' => 60,
        ],
    ],

    'cleanup' => [
        'enabled' => true,
        'batch_size' => 100,
        'expire_check_interval_minutes' => 5,
    ],

    'monitoring' => [
        'alert_high_utilization_percent' => 90,
        'alert_queue_exceeds' => 20,
        'track_history_days' => 30,
    ],
];
