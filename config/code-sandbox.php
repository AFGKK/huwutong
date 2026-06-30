<?php

return [
    /*
    | 代码沙箱配置
    | 安全的在线代码执行环境
    */

    // 是否启用沙箱
    'enabled' => env('CODE_SANDBOX_ENABLED', true),

    // 执行超时（秒）
    'timeout' => env('CODE_SANDBOX_TIMEOUT', 10),

    // 最大输出大小（字节）
    'max_output_size' => 1024 * 100, // 100KB

    // 最大输入代码大小（字符）
    'max_code_length' => 5000,

    // 最大内存限制（MB）
    'memory_limit' => 128,

    // 临时工作目录
    'work_dir' => storage_path('app/sandbox'),

    // 允许的语言
    'languages' => [
        'php' => [
            'enabled' => true,
            'binary' => env('CODE_SANDBOX_PHP', 'php'),
            'extension' => 'php',
            'version_cmd' => 'php -v',
            'disabled_functions' => [
                'exec', 'shell_exec', 'system', 'passthru', 'popen', 'proc_open',
                'pcntl_exec', 'eval', 'assert', 'create_function',
                'file_put_contents', 'file_get_contents', 'fopen', 'fwrite',
                'unlink', 'rmdir', 'mkdir', 'chmod', 'chown',
                'curl_exec', 'curl_multi_exec',
                'mail', 'fsockopen', 'pfsockopen',
                'dl', 'ini_set', 'set_time_limit',
                'mysql_connect', 'mysqli_connect', 'pg_connect',
                'socket_create', 'stream_socket_server',
            ],
        ],
        'python' => [
            'enabled' => env('CODE_SANDBOX_PYTHON_ENABLED', true),
            'binary' => env('CODE_SANDBOX_PYTHON', 'python3'),
            'extension' => 'py',
            'version_cmd' => 'python3 --version',
        ],
        'node' => [
            'enabled' => env('CODE_SANDBOX_NODE_ENABLED', true),
            'binary' => env('CODE_SANDBOX_NODE', 'node'),
            'extension' => 'js',
            'version_cmd' => 'node --version',
        ],
        'bash' => [
            'enabled' => env('CODE_SANDBOX_BASH_ENABLED', false),
            'binary' => env('CODE_SANDBOX_BASH', 'bash'),
            'extension' => 'sh',
            'version_cmd' => 'bash --version',
        ],
        'sql' => [
            'enabled' => env('CODE_SANDBOX_SQL_ENABLED', true),
            'driver' => 'sqlite',
            'extension' => 'sql',
            'version_cmd' => null,
        ],
    ],
];
