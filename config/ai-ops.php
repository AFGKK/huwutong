<?php

// M2-42 AI 运营分析助手 配置

return [
    /*
    |--------------------------------------------------------------------------
    | 默认 LLM 模型参数
    |--------------------------------------------------------------------------
    */
    'llm' => [
        'temperature' => 0.1,
        'max_tokens' => 1000,
    ],

    /*
    |--------------------------------------------------------------------------
    | 图表类型映射
    |--------------------------------------------------------------------------
    */
    'chart_types' => [
        'bar' => '柱状图',
        'line' => '折线图',
        'pie' => '饼图',
        'table' => '数据表格',
        'number' => '数值卡片',
        'trend' => '趋势图',
    ],

    /*
    |--------------------------------------------------------------------------
    | 预置分析模板
    |--------------------------------------------------------------------------
    */
    'templates' => [
        // License 分析
        ['key' => 'activation_trend', 'name' => '激活趋势', 'description' => '查看最近 N 天的 License 激活趋势', 'category' => 'license'],
        ['key' => 'activation_by_product', 'name' => '按产品统计激活', 'description' => '统计各产品的激活数量排行', 'category' => 'license'],
        ['key' => 'license_status_dist', 'name' => 'License 状态分布', 'description' => '查看各状态 License 的数量分布', 'category' => 'license'],
        ['key' => 'expiring_soon', 'name' => '即将过期', 'description' => '查看未来 7 天内即将过期的 License', 'category' => 'license'],

        // 客户分析
        ['key' => 'top_customers', 'name' => '客户排行', 'description' => '按 License 数量排列客户排行', 'category' => 'customer'],
        ['key' => 'customer_growth', 'name' => '客户增长', 'description' => '查看每月新增客户数趋势', 'category' => 'customer'],
        ['key' => 'geo_distribution', 'name' => '客户地域分布', 'description' => '按地区统计客户分布', 'category' => 'customer'],

        // 设备分析
        ['key' => 'device_by_platform', 'name' => '设备平台分布', 'description' => '按操作系统统计设备分布', 'category' => 'device'],
        ['key' => 'active_devices', 'name' => '活跃设备趋势', 'description' => '查看每日活跃设备趋势', 'category' => 'device'],

        // 订阅分析
        ['key' => 'mrr_trend', 'name' => 'MRR 趋势', 'description' => '查看月度经常性收入趋势', 'category' => 'subscription'],
        ['key' => 'subscription_by_plan', 'name' => '订阅方案分布', 'description' => '按方案统计订阅数量', 'category' => 'subscription'],
        ['key' => 'churn_rate', 'name' => '流失率', 'description' => '查看月度客户流失率', 'category' => 'subscription'],

        // 收入分析
        ['key' => 'revenue_summary', 'name' => '收入概览', 'description' => '查看总收入、本月收入、上月收入', 'category' => 'revenue'],
        ['key' => 'revenue_by_product', 'name' => '按产品收入', 'description' => '按产品统计收入排行', 'category' => 'revenue'],
    ],

    /*
    |--------------------------------------------------------------------------
    | 允许查询的业务表
    |--------------------------------------------------------------------------
    */
    'allowed_tables' => [
        'licenses', 'license_activations', 'customers', 'devices',
        'subscriptions', 'invoices', 'products', 'users',
        'refunds', 'webhook_events',
    ],
];
