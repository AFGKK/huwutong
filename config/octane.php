<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Octane Server
    |--------------------------------------------------------------------------
    |
    | Choose between 'roadrunner' (Go binary) or 'swoole' (PHP extension).
    | Swoole is used here for simplicity on Alpine Docker.
    |
    */
    'server' => env('OCTANE_SERVER', 'swoole'),

    /*
    |--------------------------------------------------------------------------
    | Octane Workers
    |--------------------------------------------------------------------------
    |
    | Number of workers. On benchmark, set to number of CPU cores * 2.
    | Default 16 for 8-core host.
    |
    */
    'workers' => env('OCTANE_WORKERS', 16),

    /*
    |--------------------------------------------------------------------------
    | Max Requests Per Worker
    |--------------------------------------------------------------------------
    |
    | Workers are recycled after processing this many requests to prevent
    | memory leaks. 5000 is a good balance for high-throughput benchmarks.
    |
    */
    'max_requests' => env('OCTANE_MAX_REQUESTS', 5000),

    /*
    |--------------------------------------------------------------------------
    | Octane Watcher
    |--------------------------------------------------------------------------
    |
    | File watcher for development. Disabled in benchmark/production.
    |
    */
    'watch' => [
        'projects' => [],
        'paths' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Octane Garbage Collection
    |--------------------------------------------------------------------------
    */
    'gc' => [
        'interval' => 50,
        'thresholds' => [
            'cycle' => 500,
            'memory' => 100,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Octane State
    |--------------------------------------------------------------------------
    */
    'state' => [
        'path' => base_path('bootstrap/cache/octane'),
        'dump' => base_path('bootstrap/cache/octane-dump.php'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Octane Warmup
    |--------------------------------------------------------------------------
    */
    'warmup' => [
        'collectors' => [],
        'paths' => [
            realpath(__DIR__ . '/../app'),
            realpath(__DIR__ . '/../config'),
            realpath(__DIR__ . '/../routes'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Octane Listeners
    |--------------------------------------------------------------------------
    */
    'listeners' => [],

    /*
    |--------------------------------------------------------------------------
    | Swoole Tables
    |--------------------------------------------------------------------------
    */
    'tables' => [],

    /*
    |--------------------------------------------------------------------------
    | Swoole Settings
    |--------------------------------------------------------------------------
    */
    'swoole' => [
        'options' => [
            'log_level' => 4,  // SWOOLE_LOG_ERROR
            'http_compression' => false,
            'http_compression_level' => 0,
            'package_max_length' => 10 * 1024 * 1024, // 10MB
            'buffer_output_size' => 10 * 1024 * 1024, // 10MB
            'socket_buffer_size' => 8 * 1024 * 1024,   // 8MB
        ],
    ],
];
