<?php

// M2-52 SIEM 审计日志导出 配置

return [
    /*
    |--------------------------------------------------------------------------
    | 支持的 SIEM 格式
    |--------------------------------------------------------------------------
    */
    'formats' => [
        'cef' => 'Splunk CEF (Common Event Format)',
        'elk_json' => 'ELK Stack JSON',
        'sls' => '阿里云 SLS (Log Service)',
    ],

    /*
    |--------------------------------------------------------------------------
    | 默认格式
    |--------------------------------------------------------------------------
    */
    'default_format' => env('SIEM_DEFAULT_FORMAT', 'elk_json'),

    /*
    |--------------------------------------------------------------------------
    | 最大推送记录数/批次
    |--------------------------------------------------------------------------
    */
    'max_records_per_push' => env('SIEM_MAX_RECORDS_PER_PUSH', 5000),

    /*
    |--------------------------------------------------------------------------
    | 推送超时(秒)
    |--------------------------------------------------------------------------
    */
    'push_timeout' => env('SIEM_PUSH_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | CEF 厂商/产品标识（Splunk CEF 头部）
    |--------------------------------------------------------------------------
    */
    'cef' => [
        'vendor' => 'Huwutong',
        'product' => 'LicenseManager',
        'version' => '1.0',
        'device_event_class_id' => '100',
        'severity_map' => [
            'emergency' => 10,
            'alert' => 9,
            'critical' => 8,
            'error' => 7,
            'warning' => 5,
            'notice' => 4,
            'info' => 3,
            'debug' => 1,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | ELK JSON 默认索引
    |--------------------------------------------------------------------------
    */
    'elk' => [
        'default_index' => 'huwutong-audit',
        'index_prefix' => 'huwutong-',
    ],

    /*
    |--------------------------------------------------------------------------
    | 字段映射（audit_log字段 -> SIEM字段）
    |--------------------------------------------------------------------------
    */
    'field_mappings' => [
        'cef' => [
            'id' => 'cs1',
            'event_type' => 'cs2',
            'tenant_id' => 'cs3',
            'user_id' => 'cs4',
            'ip_address' => 'src',
            'user_agent' => 'cs5',
            'description' => 'msg',
            'created_at' => 'rt',
            'severity' => 'cn1',
        ],
        'elk_json' => [
            'id' => '@id',
            'event_type' => 'event.type',
            'tenant_id' => 'tenant.id',
            'user_id' => 'user.id',
            'ip_address' => 'network.client.ip',
            'user_agent' => 'network.client.user_agent',
            'description' => 'message',
            'created_at' => '@timestamp',
            'severity' => 'event.severity',
            'metadata' => 'metadata',
            'changes' => 'audit.changes',
        ],
        'sls' => [
            'id' => '__id__',
            'event_type' => 'event_type',
            'tenant_id' => 'tenant_id',
            'user_id' => 'user_id',
            'ip_address' => 'ip_address',
            'user_agent' => 'user_agent',
            'description' => 'description',
            'created_at' => '__time__',
            'severity' => 'severity',
        ],
    ],
];
