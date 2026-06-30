<?php

// M3-30 客户自助数据导出配置

return [
    'types' => [
        'licenses' => [
            'label' => 'License 列表',
            'description' => '导出客户名下所有 License 信息',
            'enabled' => true,
        ],
        'activations' => [
            'label' => '激活记录',
            'description' => '导出所有 License 的激活/设备记录',
            'enabled' => true,
        ],
        'invoices' => [
            'label' => '发票/账单',
            'description' => '导出客户的发票和账单历史',
            'enabled' => true,
        ],
        'devices' => [
            'label' => '设备列表',
            'description' => '导出已注册的设备信息',
            'enabled' => false,
        ],
    ],

    'formats' => ['csv', 'pdf', 'xlsx'],

    'limits' => [
        'max_records' => env('DATA_EXPORT_MAX_RECORDS', 10000),
        'cooldown_seconds' => env('DATA_EXPORT_COOLDOWN', 60),
        'export_expiry_hours' => 24,
        'max_daily_exports' => 20,
    ],

    'storage' => [
        'disk' => env('DATA_EXPORT_DISK', 'local'),
        'path' => 'exports/customer',
    ],

    'admin' => [
        'can_export_all_customers' => true,
        'default_format' => 'csv',
        'max_records_admin' => 50000,
    ],
];
