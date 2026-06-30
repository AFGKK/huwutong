<?php

return [
    /*
    | 密钥管理器驱动
    | 支持: local, kms, vault
    | local: 使用 Laravel APP_KEY 进行本地加密
    | kms: 使用 AWS KMS / 阿里云 KMS（需配置 SDK）
    | vault: 使用 HashiCorp Vault Transit Engine
    */
    'driver' => env('SECRET_MANAGER_DRIVER', 'local'),

    /*
    | AWS KMS 配置
    */
    'kms_key_id' => env('AWS_KMS_KEY_ID'),
    'kms_region' => env('AWS_KMS_REGION', 'us-east-1'),

    /*
    | HashiCorp Vault 配置
    */
    'vault_addr' => env('VAULT_ADDR'),
    'vault_token' => env('VAULT_TOKEN'),
    'vault_transit_path' => env('VAULT_TRANSIT_PATH', 'transit'),

    /*
    | 密钥自动轮换
    | 主密钥有效期（天），到期前自动提醒轮换
    */
    'key_rotation_days' => env('SECRET_KEY_ROTATION_DAYS', 365),

    /*
    | 凭据过期默认天数
    | 创建凭据时未指定 expires_at 时的默认值
    */
    'default_expiry_days' => env('SECRET_DEFAULT_EXPIRY_DAYS', 730), // 2 年
];
