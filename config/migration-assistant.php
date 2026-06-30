<?php

// M3-39 AI 迁移助手配置

return [
    'sources' => [
        'cryptlex' => [
            'name' => 'Cryptlex',
            'enabled' => true,
            'api_base' => 'https://api.cryptlex.com/v3',
            'import_endpoints' => [
                'licenses' => '/licenses',
                'products' => '/products',
                'customers' => '/customers',
            ],
            'field_mapping' => [
                'key' => 'license_key',
                'status' => 'status',
                'expiresAt' => 'expires_at',
                'customer.email' => 'customer_email',
                'product.name' => 'product_name',
                'allowedMachines' => 'max_devices',
                'metadata' => 'metadata',
                'createdAt' => 'created_at',
            ],
        ],
        'localazy' => [
            'name' => 'Localazy',
            'enabled' => true,
            'api_base' => 'https://api.localazy.com/v1',
            'import_endpoints' => [
                'licenses' => '/licenses',
                'apps' => '/apps',
            ],
            'field_mapping' => [
                'id' => 'license_key',
                'active' => 'status',
                'validUntil' => 'expires_at',
                'user.email' => 'customer_email',
                'app.name' => 'product_name',
                'seats' => 'max_devices',
                'created' => 'created_at',
            ],
        ],
    ],

    'ai' => [
        'enabled' => env('MIGRATION_AI_ENABLED', false),
        'model' => env('MIGRATION_AI_MODEL', 'gpt-4'),
        'tasks' => [
            'field_mapping' => true,
            'data_cleaning' => true,
            'validation' => true,
            'transformation' => true,
        ],
    ],

    'migration' => [
        'batch_size' => 50,
        'validate_before_import' => true,
        'dry_run_default' => true,
        'rollback_on_failure' => false,
        'create_missing_products' => true,
        'create_missing_customers' => true,
    ],

    'validation' => [
        'rules' => [
            'license_key' => 'required|unique',
            'status' => 'in:active,expired,revoked,suspended,trial',
            'expires_at' => 'nullable|date',
            'customer_email' => 'nullable|email',
        ],
        'auto_fix' => true,
        'max_errors_before_stop' => 100,
    ],
];
