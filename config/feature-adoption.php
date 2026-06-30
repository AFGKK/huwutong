<?php

// M2-60 功能使用率追踪 (Feature Adoption) 配置

return [
    /*
    |--------------------------------------------------------------------------
    | 事件采集配置
    |--------------------------------------------------------------------------
    */
    'tracking' => [
        // 是否启用前端埋点
        'enabled' => env('FEATURE_TRACKING_ENABLED', true),
        // 采样率 (1-100)
        'sample_rate' => env('FEATURE_SAMPLE_RATE', 100),
        // 保留天数
        'retention_days' => 365,
    ],

    /*
    |--------------------------------------------------------------------------
    | 预定义功能分类
    |--------------------------------------------------------------------------
    */
    'categories' => [
        'license' => '授权管理',
        'device' => '设备管理',
        'customer' => '客户管理',
        'product' => '产品管理',
        'billing' => '计费账单',
        'security' => '安全风控',
        'report' => '报表分析',
        'settings' => '系统设置',
        'analytics' => '数据分析',
        'ai' => 'AI 功能',
        'marketing' => '营销推广',
        'automation' => '自动化工具',
    ],

    /*
    |--------------------------------------------------------------------------
    | 预定义跟踪功能点
    |--------------------------------------------------------------------------
    */
    'features' => [
        'license_create' => ['name' => '创建License', 'category' => 'license'],
        'license_activate' => ['name' => '激活License', 'category' => 'license'],
        'license_revoke' => ['name' => '吊销License', 'category' => 'license'],
        'license_export' => ['name' => '导出License', 'category' => 'license'],
        'license_batch' => ['name' => '批量操作', 'category' => 'license'],
        'device_list' => ['name' => '查看设备', 'category' => 'device'],
        'device_trust' => ['name' => '设备信任', 'category' => 'device'],
        'device_kick' => ['name' => '踢出设备', 'category' => 'device'],
        'customer_create' => ['name' => '新建客户', 'category' => 'customer'],
        'customer_import' => ['name' => '导入客户', 'category' => 'customer'],
        'product_create' => ['name' => '新建产品', 'category' => 'product'],
        'product_feature' => ['name' => '功能配置', 'category' => 'product'],
        'invoice_view' => ['name' => '查看账单', 'category' => 'billing'],
        'invoice_export' => ['name' => '导出账单', 'category' => 'billing'],
        'subscription' => ['name' => '订阅管理', 'category' => 'billing'],
        'mfa_setup' => ['name' => 'MFA配置', 'category' => 'security'],
        'api_key' => ['name' => 'API Key管理', 'category' => 'security'],
        'audit_log' => ['name' => '审计日志', 'category' => 'security'],
        'report_view' => ['name' => '查看报表', 'category' => 'report'],
        'report_schedule' => ['name' => '定时报表', 'category' => 'report'],
        'site_settings' => ['name' => '站点设置', 'category' => 'settings'],
        'email_template' => ['name' => '邮件模板', 'category' => 'settings'],
        'role_permission' => ['name' => '权限管理', 'category' => 'settings'],
        'dashboard_view' => ['name' => '仪表盘', 'category' => 'analytics'],
        'ai_chat' => ['name' => 'AI对话', 'category' => 'ai'],
        'ai_report' => ['name' => 'AI报告', 'category' => 'ai'],
        'nps_survey' => ['name' => '满意度调查', 'category' => 'marketing'],
        'webhook_config' => ['name' => 'Webhook配置', 'category' => 'automation'],
        'batch_operation' => ['name' => '批量操作', 'category' => 'automation'],
    ],

    /*
    |--------------------------------------------------------------------------
    | 漏斗定义
    |--------------------------------------------------------------------------
    */
    'funnels' => [
        'license_lifecycle' => [
            'name' => 'License 生命周期',
            'steps' => ['license_create', 'license_activate', 'license_revoke'],
        ],
        'customer_onboarding' => [
            'name' => '客户引入流程',
            'steps' => ['customer_create', 'product_create', 'license_create', 'license_activate'],
        ],
        'security_setup' => [
            'name' => '安全配置流程',
            'steps' => ['mfa_setup', 'api_key', 'audit_log'],
        ],
    ],
];
