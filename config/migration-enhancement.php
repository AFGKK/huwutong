<?php

// M3-71 竞品迁移工具增强配置

return [
    'sources' => [
        'keygen' => [
            'name' => 'Keygen.sh',
            'enabled' => true,
            'api_base' => 'https://api.keygen.sh/v1',
            'import_fields' => ['license_key', 'status', 'expiry', 'metadata', 'user_email', 'product_name'],
            'field_mapping' => [
                'license_key' => 'license_key',
                'status' => 'status',
                'expiry' => 'expires_at',
                'metadata' => 'metadata',
            ],
        ],
        'licensespring' => [
            'name' => 'LicenseSpring',
            'enabled' => true,
            'api_base' => 'https://api.licensespring.com/v1',
            'import_fields' => ['licenseKey', 'licenseStatus', 'validUntil', 'customerEmail', 'productCode'],
            'field_mapping' => [
                'licenseKey' => 'license_key',
                'licenseStatus' => 'status',
                'validUntil' => 'expires_at',
                'customerEmail' => 'customer_email',
                'productCode' => 'product_code',
            ],
        ],
        'custom' => [
            'name' => '自定义CSV/JSON',
            'enabled' => true,
            'import_fields' => ['*'],
            'max_file_size_mb' => 10,
            'allowed_formats' => ['csv', 'json', 'xlsx'],
        ],
    ],

    'import' => [
        'batch_size' => 100,
        'max_concurrent_imports' => 3,
        'default_status' => 'active',
        'create_customers' => true,
        'create_products' => true,
        'skip_duplicates' => true,
        'rollback_on_failure' => false,
    ],

    'validation' => [
        'require_valid_email' => true,
        'require_product' => true,
        'strict_mode' => false,
        'max_errors_before_abort' => 50,
    ],

    'report' => [
        'generate_after_import' => true,
        'include_failed_rows' => true,
        'format' => 'json',
    ],
];
