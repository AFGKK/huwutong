<?php

// M2-18~20 SDK 管理配置

return [
    /*
    |--------------------------------------------------------------------------
    | SDK 版本信息
    |--------------------------------------------------------------------------
    */
    'versions' => [
        'php' => [
            'name' => 'PHP SDK',
            'version' => '1.0.0',
            'min_php_version' => '8.1',
            'status' => 'stable', // stable, beta, deprecated
            'repository' => 'https://github.com/huwutong/huwutong-sdk-php',
            'install_command' => 'composer require huwutong/sdk',
        ],
        'node' => [
            'name' => 'Node.js SDK',
            'version' => '1.0.0',
            'min_node_version' => '18',
            'status' => 'stable',
            'repository' => 'https://github.com/huwutong/huwutong-sdk-node',
            'install_command' => 'npm install huwutong-sdk',
        ],
        'python' => [
            'name' => 'Python SDK',
            'version' => '1.0.0',
            'min_python_version' => '3.8',
            'status' => 'stable',
            'repository' => 'https://github.com/huwutong/huwutong-sdk-python',
            'install_command' => 'pip install huwutong-sdk',
        ],
        'go' => [
            'name' => 'Go SDK',
            'version' => '1.0.0',
            'min_go_version' => '1.18',
            'status' => 'stable',
            'repository' => 'https://github.com/huwutong/huwutong-sdk-go',
            'install_command' => 'go get github.com/huwutong/huwutong-sdk-go',
        ],
        'java' => [
            'name' => 'Java SDK',
            'version' => '1.0.0',
            'min_java_version' => '11',
            'status' => 'stable',
            'repository' => 'https://github.com/huwutong/huwutong-sdk-java',
            'install_command' => 'mvn install com.huwutong:huwutong-sdk:1.0.0',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SDK 功能矩阵
    |--------------------------------------------------------------------------
    */
    'features' => [
        'activate' => ['php', 'node', 'python', 'go', 'java'],
        'validate' => ['php', 'node', 'python', 'go', 'java'],
        'deactivate' => ['php', 'node', 'python', 'go', 'java'],
        'offline_verify' => ['php', 'node', 'python', 'go', 'java'],
        'check_feature' => ['php', 'node', 'python', 'go', 'java'],
        'heartbeat' => ['php', 'node', 'python', 'go'],
    ],
];
