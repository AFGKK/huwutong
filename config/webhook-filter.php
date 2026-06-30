<?php

// M2-53 条件化 Webhook 过滤器 配置

return [
    /*
    |--------------------------------------------------------------------------
    | 启用/禁用
    |--------------------------------------------------------------------------
    */
    'enabled' => env('WEBHOOK_FILTER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | 每端点最多过滤器数量
    |--------------------------------------------------------------------------
    */
    'max_filters_per_endpoint' => env('WEBHOOK_FILTER_MAX_PER_ENDPOINT', 20),

    /*
    |--------------------------------------------------------------------------
    | 支持的操作符
    |--------------------------------------------------------------------------
    */
    'operators' => [
        'equals',        // 精确匹配
        'not_equals',    // 不匹配
        'contains',      // 包含
        'not_contains',  // 不包含
        'starts_with',   // 开头
        'ends_with',     // 结尾
        'in',            // 在列表中 (逗号分隔)
        'not_in',        // 不在列表中
        'greater_than',  // 大于 (数值)
        'less_than',     // 小于 (数值)
        'exists',        // 字段存在
        'not_exists',    // 字段不存在
        'regex',         // 正则匹配
    ],

    /*
    |--------------------------------------------------------------------------
    | 支持的筛选字段 (Payload 中的字段路径)
    |--------------------------------------------------------------------------
    */
    'supported_fields' => [
        'event_type',
        'license.status',
        'license.product_id',
        'license.customer_id',
        'license.tenant_id',
        'license.expires_at',
        'device.platform',
        'device.fingerprint',
        'customer.email',
        'customer.name',
        'customer.tier',
        'metadata.*', // 自定义字段通配
    ],

    /*
    |--------------------------------------------------------------------------
    | Payload 模板变量
    |--------------------------------------------------------------------------
    */
    'template_variables' => [
        '{{event_type}}',
        '{{event_time}}',
        '{{tenant_id}}',
        '{{license.id}}',
        '{{license.key}}',
        '{{license.status}}',
        '{{license.expires_at}}',
        '{{customer.id}}',
        '{{customer.name}}',
        '{{customer.email}}',
        '{{device.fingerprint}}',
        '{{device.platform}}',
        '{{raw_payload}}',
    ],
];
