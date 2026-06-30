<?php

/**
 * 自定义字段配置 (M3-46)
 */
return [
    /*
    |--------------------------------------------------------------------------
    | 支持的目标实体
    |--------------------------------------------------------------------------
    */
    'entity_types' => ['license', 'customer', 'product'],

    /*
    |--------------------------------------------------------------------------
    | 字段类型
    |--------------------------------------------------------------------------
    */
    'field_types' => [
        'text' => ['label' => '单行文本', 'has_options' => false],
        'textarea' => ['label' => '多行文本', 'has_options' => false],
        'number' => ['label' => '数字', 'has_options' => false],
        'select' => ['label' => '下拉选择', 'has_options' => true],
        'multi_select' => ['label' => '多选', 'has_options' => true],
        'date' => ['label' => '日期', 'has_options' => false],
        'boolean' => ['label' => '布尔', 'has_options' => false],
        'url' => ['label' => '链接', 'has_options' => false],
        'email' => ['label' => '邮箱', 'has_options' => false],
        'color' => ['label' => '颜色', 'has_options' => false],
    ],

    /*
    |--------------------------------------------------------------------------
    | API 透传配置（自定义字段值自动附加到 API 响应和 Webhook 中）
    |--------------------------------------------------------------------------
    */
    'api_passthrough' => [
        'enabled' => true,
        'license_response' => true,     // License 列表/详情 API 自动附带自定义字段
        'customer_response' => true,    // 客户列表/详情 API 自动附带
        'product_response' => true,     // 产品列表/详情 API 自动附带
        'webhook_payload' => true,      // Webhook 事件负载中包含自定义字段
        'response_key' => 'custom_fields', // JSON 响应中的 key 名
    ],

    /*
    |--------------------------------------------------------------------------
    | 字段限制
    |--------------------------------------------------------------------------
    */
    'limits' => [
        'max_fields_per_entity' => 50,      // 每个实体最多字段数
        'max_text_length' => 5000,
        'max_textarea_length' => 50000,
        'max_options' => 50,                // select/multi_select 最大选项数
        'max_option_length' => 100,
    ],
];
