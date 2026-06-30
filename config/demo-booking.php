<?php

// M2-98 预约Demo/联系销售 配置

return [
    /*
    |--------------------------------------------------------------------------
    | 表单配置
    |--------------------------------------------------------------------------
    */
    'form' => [
        'fields' => [
            'company_name' => ['label' => '公司名称', 'required' => true, 'type' => 'text'],
            'contact_name' => ['label' => '联系人', 'required' => true, 'type' => 'text'],
            'email' => ['label' => '邮箱', 'required' => true, 'type' => 'email'],
            'phone' => ['label' => '手机号', 'required' => false, 'type' => 'text'],
            'employee_count' => ['label' => '员工规模', 'required' => false, 'type' => 'select', 'options' => ['1-10', '11-50', '51-200', '201-1000', '1000+']],
            'product_interest' => ['label' => '感兴趣的产品', 'required' => false, 'type' => 'multiselect', 'options' => ['License授权', '设备管理', 'API/SDK', '安全风控', '企业版全套']],
            'message' => ['label' => '备注信息', 'required' => false, 'type' => 'textarea'],
        ],
        'honeypot' => 'website_url',
        'rate_limit' => 3, // 每IP每小时
    ],

    /*
    |--------------------------------------------------------------------------
    | Calendly 集成
    |--------------------------------------------------------------------------
    */
    'calendly' => [
        'enabled' => env('CALENDLY_ENABLED', false),
        'api_key' => env('CALENDLY_API_KEY', ''),
        'event_type_uuid' => env('CALENDLY_EVENT_TYPE', ''),
        'organization_url' => env('CALENDLY_ORG_URL', 'https://calendly.com/huwutong'),
    ],

    /*
    |--------------------------------------------------------------------------
    | CRM 线索创建
    |--------------------------------------------------------------------------
    */
    'crm' => [
        'enabled' => env('DEMO_CRM_ENABLED', false),
        'type' => env('DEMO_CRM_TYPE', 'internal'), // internal / hubspot / salesforce
        'hubspot_api_key' => env('HUBSPOT_API_KEY', ''),
        'salesforce' => [
            'client_id' => env('SALESFORCE_CLIENT_ID', ''),
            'client_secret' => env('SALESFORCE_CLIENT_SECRET', ''),
            'username' => env('SALESFORCE_USERNAME', ''),
            'password' => env('SALESFORCE_PASSWORD', ''),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 通知配置
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        'admin_email' => env('DEMO_ADMIN_EMAIL', 'sales@huwutong.com'),
        'slack_webhook' => env('DEMO_SLACK_WEBHOOK', ''),
        'subject_prefix' => '[Demo预约] ',
    ],

    /*
    |--------------------------------------------------------------------------
    | 预约状态
    |--------------------------------------------------------------------------
    */
    'statuses' => [
        'pending' => '待处理',
        'contacted' => '已联系',
        'scheduled' => '已预约',
        'completed' => '已完成',
        'converted' => '已转化',
        'lost' => '已流失',
    ],
];
