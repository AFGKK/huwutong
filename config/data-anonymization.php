<?php

return [
    /*
    |--------------------------------------------------------------------------
    | 数据匿名化导出配置 (M2-139)
    |--------------------------------------------------------------------------
    */

    /*
    | Staging 数据库连接名
    */
    'staging_connection' => env('ANONYMIZE_STAGING_CONNECTION', 'mysql_staging'),

    /*
    | 默认匿名化规则
    | 格式: 表名 => [字段名 => 匿名化方法]
    */
    'default_rules' => [
        'users' => [
            'name' => 'chinese_name',
            'email' => 'email',
            'phone' => 'phone',
            'mobile' => 'phone',
            'password' => 'fixed_value',
            'password_history' => 'fixed_value',
            'mfa_secret' => 'fixed_value',
            'mfa_recovery_codes' => 'fixed_value',
            'avatar' => 'fixed_value',
            'remember_token' => 'fixed_value',
        ],
        'customers' => [
            'name' => 'chinese_name',
            'email' => 'email',
            'phone' => 'phone',
            'address' => 'address',
            'city' => 'city',
            'state' => 'state',
            'postal_code' => 'postal_code',
            'notes' => 'sentence',
        ],
        'tenants' => [
            'name' => 'company_name',
            'domain' => 'domain',
            'logo_url' => 'fixed_value',
        ],
        'tenant_members' => [
            'role_name' => 'job_title',
        ],
        'invoices' => [
            'billing_name' => 'chinese_name',
            'billing_company' => 'company_name',
            'billing_address_line1' => 'address',
            'billing_address_line2' => 'address',
            'billing_city' => 'city',
            'billing_state' => 'state',
            'billing_zip' => 'postal_code',
            'billing_phone' => 'phone',
            'billing_email' => 'email',
            'notes' => 'sentence',
        ],
        'license_notes' => [
            'content' => 'sentence',
        ],
        'api_keys' => [
            'name' => 'sentence',
            'key' => 'token',
        ],
        'activity_log' => [
            'ip_address' => 'ip',
            'user_agent' => 'fixed_value',
        ],
        'audit_logs' => [
            'ip_address' => 'ip',
            'user_agent' => 'fixed_value',
        ],
        'tickets' => [
            'subject' => 'sentence',
            'description' => 'paragraph',
        ],
        'notes' => [
            'title' => 'sentence',
            'content' => 'paragraph',
        ],
        'subscribers' => [
            'email' => 'email',
            'name' => 'chinese_name',
        ],
    ],

    /*
    | 匿名化方法配置
    | 每种方法使用 Faker 的 formatter 名称
    */
    'methods' => [
        'chinese_name' => 'zh_CN\\', // 使用 zh_CN 的 name formatter
        'email' => 'email',
        'phone' => 'phoneNumber',
        'address' => 'address',
        'city' => 'city',
        'state' => 'state',
        'postal_code' => 'postcode',
        'company_name' => 'company',
        'job_title' => 'jobTitle',
        'domain' => 'domainName',
        'sentence' => 'sentence',
        'paragraph' => 'paragraph',
        'token' => 'md5',
        'ip' => 'ipv4',
    ],

    /*
    | 固定值替换 (用于 password/token 等字段)
    */
    'fixed_value' => env('ANONYMIZE_FIXED_VALUE', '[ANONYMIZED]'),

    /*
    | 导出批次大小
    */
    'chunk_size' => (int) env('ANONYMIZE_CHUNK_SIZE', 500),

    /*
    | 排除的表（不导出也不匿名化）
    */
    'exclude_tables' => explode(',', env('ANONYMIZE_EXCLUDE_TABLES', 'cache, cache_locks, sessions, jobs, job_batches, failed_jobs, webhook_calls, temporary_files')),

    /*
    | 排除的表（仅导出结构，不导数据）
    */
    'schema_only_tables' => explode(',', env('ANONYMIZE_SCHEMA_ONLY', 'audit_logs, activity_logs, cdn_distributions, update_package_downloads')),

    /*
    | 自动清空的表（仅创建结构，不导出数据）
    */
    'truncate_tables' => explode(',', env('ANONYMIZE_TRUNCATE_TABLES', 'password_reset_tokens, personal_access_tokens, cache, cache_locks, sessions')),
];

