<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Zapier/Make 无代码集成配置
    |--------------------------------------------------------------------------
    */
    'enabled' => env('ZAPIER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | API 密钥验证
    |--------------------------------------------------------------------------
    */
    'api_key' => env('ZAPIER_API_KEY', ''),
    'allowed_ips' => explode(',', env('ZAPIER_ALLOWED_IPS', '')),

    /*
    |--------------------------------------------------------------------------
    | 预建工作流模板
    |--------------------------------------------------------------------------
    */
    'workflow_templates' => [
        [
            'id' => 'license-expiry-alert',
            'platform' => 'both',
            'title' => 'License 到期提醒 → 发送邮件通知',
            'description' => '当 License 剩余 30 天到期时，自动发送邮件提醒客户续费',
            'trigger' => 'License 即将到期',
            'actions' => ['发送邮件通知', '创建跟进工单'],
            'category' => '续费管理',
        ],
        [
            'id' => 'new-license-slack',
            'platform' => 'both',
            'title' => '新 License 创建 → Slack 通知销售团队',
            'description' => '当创建新 License 时，自动发送 Slack 消息通知销售团队',
            'trigger' => '新 License 创建',
            'actions' => ['发送 Slack 通知'],
            'category' => '通知',
        ],
        [
            'id' => 'suspension-to-crm',
            'platform' => 'both',
            'title' => 'License 挂起 → CRM 更新客户状态',
            'description' => '当 License 被挂起时，自动更新 CRM 中客户状态为"暂停"',
            'trigger' => 'License 挂起',
            'actions' => ['更新 CRM 记录', '发送告警通知'],
            'category' => 'CRM',
        ],
        [
            'id' => 'new-customer-welcome',
            'platform' => 'both',
            'title' => '新客户注册 → 自动发送欢迎邮件序列',
            'description' => '当创建新客户时，自动发送欢迎邮件序列并创建入门工单',
            'trigger' => '新客户创建',
            'actions' => ['发送欢迎邮件', '创建入门工单', '分配客户成功经理'],
            'category' => '客户 onboarding',
        ],
        [
            'id' => 'activation-log-to-sheet',
            'platform' => 'both',
            'title' => 'License 激活 → 记录到 Google Sheets',
            'description' => '每次 License 激活时，将激活记录追加到 Google Sheets 用于审计',
            'trigger' => 'License 激活',
            'actions' => ['追加 Google Sheets 行'],
            'category' => '审计',
        ],
        [
            'id' => 'expired-bulk-suspension',
            'platform' => 'both',
            'title' => '批量挂起过期 License → 生成报告',
            'description' => '自动检查所有过期 License，批量挂起并生成 CSV 报告发送给管理员',
            'trigger' => '定时 (每天)',
            'actions' => ['批量查询过期 License', '批量挂起', '生成 CSV', '发送邮件报告'],
            'category' => '运维',
        ],
        [
            'id' => 'new-device-alert',
            'platform' => 'both',
            'title' => '新设备激活 → 安全团队告警',
            'description' => '当从未知设备激活 License 时，发送安全告警到安全团队',
            'trigger' => '新设备激活',
            'actions' => ['检查设备是否已知', '发送安全告警'],
            'category' => '安全',
        ],
        [
            'id' => 'revenue-to-bi',
            'platform' => 'both',
            'title' => '销售收入 → 同步到 BI 系统',
            'description' => '每日将销售收入和 License 统计数据同步到 BI/数仓系统',
            'trigger' => '定时 (每天)',
            'actions' => ['获取收入数据', '同步到 BI 系统'],
            'category' => '数据分析',
        ],
        [
            'id' => 'ticket-create-from-expiry',
            'platform' => 'both',
            'title' => 'License 即将到期 → 自动创建客服工单',
            'description' => '当 License 剩余 7 天到期时，自动创建优先工单给客服团队',
            'trigger' => 'License 7 天到期',
            'actions' => ['创建 Zendesk/Jira 工单', '通知客服团队'],
            'category' => '客服',
        ],
        [
            'id' => 'multi-license-bulk',
            'platform' => 'both',
            'title' => '批量创建 License → 生成分发邮件',
            'description' => '从 CSV 批量创建 License 并自动生成包含 License Key 的分发邮件',
            'trigger' => 'CSV 上传',
            'actions' => ['解析 CSV', '批量创建 License', '生成邮件', '发送给客户'],
            'category' => '批量操作',
        ],
        [
            'id' => 'compliance-report',
            'platform' => 'both',
            'title' => '合规报告 → 自动归档',
            'description' => '每月自动生成合规报告并归档到云存储',
            'trigger' => '定时 (每月)',
            'actions' => ['生成合规报告', '上传到云存储', '通知合规团队'],
            'category' => '合规',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 轮询分页
    |--------------------------------------------------------------------------
    */
    'pagination' => [
        'default_limit' => 50,
        'max_limit' => 200,
    ],
];
