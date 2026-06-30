<?php

return [
    /*
    |--------------------------------------------------------------------------
    | HSM 硬件安全模块配置
    |--------------------------------------------------------------------------
    |
    | M3-79: License Key Ed25519/RSA 签名在 HSM 内完成
    | 私钥永不出 HSM，满足 FIPS 140-2 Level 3 合规
    |
    | 支持: software(开发), aws, azure, aliyun
    |
    | 环境变量:
    |   HSM_ENABLED=true
    |   HSM_DEFAULT=aws
    |   HSM_AWS_ENDPOINT=https://your-hsm-cluster.execute-api.region.amazonaws.com
    |   HSM_AWS_API_KEY=xxx
    |   HSM_AZURE_ENDPOINT=https://your-vault.vault.azure.net
    |   HSM_AZURE_TENANT_ID=xxx
    |   HSM_AZURE_CLIENT_ID=xxx
    |   HSM_AZURE_CLIENT_SECRET=xxx
    |   HSM_ALIYUN_ENDPOINT=https://kms.cn-hangzhou.aliyuncs.com
    |   HSM_ALIYUN_ACCESS_KEY=xxx
    |   HSM_ALIYUN_ACCESS_SECRET=xxx
    */

    'enabled' => env('HSM_ENABLED', false),

    'default' => env('HSM_DEFAULT', 'software'),

    'providers' => [
        'aws' => [
            'endpoint' => env('HSM_AWS_ENDPOINT', ''),
            'api_key' => env('HSM_AWS_API_KEY', ''),
            'timeout' => env('HSM_AWS_TIMEOUT', 10),
        ],

        'azure' => [
            'endpoint' => env('HSM_AZURE_ENDPOINT', ''),
            'tenant_id' => env('HSM_AZURE_TENANT_ID', ''),
            'client_id' => env('HSM_AZURE_CLIENT_ID', ''),
            'client_secret' => env('HSM_AZURE_CLIENT_SECRET', ''),
        ],

        'aliyun' => [
            'endpoint' => env('HSM_ALIYUN_ENDPOINT', ''),
            'access_key' => env('HSM_ALIYUN_ACCESS_KEY', ''),
            'access_secret' => env('HSM_ALIYUN_ACCESS_SECRET', ''),
        ],
    ],
];
