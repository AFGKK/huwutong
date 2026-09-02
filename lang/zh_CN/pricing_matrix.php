<?php

return [
    'unlimited' => '无限',
    'per_minute' => '/分钟',
    'check' => '✓',
    'units' => [
        'products' => '个',
        'activations' => '个',
        'keys' => '个',
        'members' => '人',
        'languages' => '种',
        'replies' => '条',
        'groups' => '个',
    ],
    'values' => [
        'webhook_retry' => [
            'retry_filter' => '重试+过滤',
            'full' => '完整',
        ],
        'sla' => [
            'negotiable' => '可协商',
            'written' => '书面约定',
        ],
        'data_export' => [
            'csv' => 'CSV',
            'csv_json' => 'CSV+JSON',
        ],
        'trial' => [
            '7' => '7 天',
            '14' => '14 天',
            '30' => '30 天',
            'custom' => '定制',
        ],
    ],
    'max_products' => [
        'label' => '产品数量',
        'tip' => '您可以在平台上发布管理的软件产品数量',
    ],
    'max_activations' => [
        'label' => '设备激活数',
        'tip' => '每月允许的终端设备激活总数（License 激活）',
    ],
    'api_rate_limit' => [
        'label' => 'API 限流',
        'tip' => 'API 请求频率限制，超出将暂时被限流',
    ],
    'max_api_keys' => [
        'label' => 'API Key',
        'tip' => '可创建的 API 密钥数量，用于 SDK 集成和身份验证',
    ],
    'team_members' => [
        'label' => '团队成员',
        'tip' => '可添加到团队协作的成员数量',
    ],
    'rbac' => [
        'label' => 'RBAC 权限管理',
        'tip' => '基于角色的访问控制（RBAC），精细化权限分配',
    ],
    'webhook' => [
        'label' => 'Webhook',
        'tip' => '支持 Webhook 回调通知，实时获取 License 事件',
    ],
    'webhook_retry_filter' => [
        'label' => 'Webhook 重试/过滤',
        'tip' => 'Webhook 自动重试、事件过滤、回放及死信监控',
    ],
    'customer_portal' => [
        'label' => '客户 Portal',
        'tip' => '为客户提供自助门户，查看和管理他们的 License',
    ],
    'multi_currency' => [
        'label' => '多币种定价',
        'tip' => '支持 CNY/USD/EUR 等多币种商品定价与结算',
    ],
    'custom_domain' => [
        'label' => '自定义域名',
        'tip' => '使用自有域名托管 License 验证服务',
    ],
    'sdk_languages' => [
        'label' => '多语言 SDK',
        'tip' => '支持多种编程语言的 SDK（PHP/Node/Python/Java/Go/.NET）',
    ],
    'offline_licensing' => [
        'label' => '离线授权',
        'tip' => '支持离线环境下的 License 生成和验证',
    ],
    'device_fingerprint' => [
        'label' => '设备指纹',
        'tip' => '基于硬件特征的设备唯一标识，防止 License 滥用',
    ],
    'floating_seats' => [
        'label' => '席位池浮动',
        'tip' => 'License 在团队设备间动态分配，不绑定固定设备',
    ],
    'oem_whitelabel' => [
        'label' => 'OEM 白标',
        'tip' => '去除互物通品牌，以您的品牌呈现 License 管理界面',
    ],
    'sso_saml' => [
        'label' => 'SSO/SAML',
        'tip' => '支持 SAML 2.0 / OIDC 单点登录，集成企业身份认证',
    ],
    'audit_logs' => [
        'label' => '审计日志',
        'tip' => '完整的操作审计日志，满足合规和安全审查需求',
    ],
    'ai_insights' => [
        'label' => 'AI 智能分析',
        'tip' => '基于 AI 的 License 使用分析和异常检测',
    ],
    'live_chat' => [
        'label' => '在线客服',
        'tip' => '网站即时通讯（Live Chat），访客对话与消息管理',
    ],
    'ai_support' => [
        'label' => 'AI 智能客服',
        'tip' => 'AI 驱动的智能回复、RAG 知识库问答、自动回复规则',
    ],
    'human_handoff' => [
        'label' => '人工转接',
        'tip' => 'AI 自动转人工客服（Handoff），支持置信度阈值/超时策略',
    ],
    'canned_replies' => [
        'label' => '快捷回复',
        'tip' => '预设快捷回复模板（Canned Replies），提升客服效率',
    ],
    'agent_groups' => [
        'label' => '客服组/部门',
        'tip' => '创建客服分组/部门，实现多团队协作和会话分配',
    ],
    'im_notifications' => [
        'label' => 'IM 通知集成',
        'tip' => '集成 Slack、钉钉、企业微信、飞书，实时通知 IM 事件',
    ],
    'sla_options' => [
        'label' => 'SLA 选项',
        'tip' => '企业版可另行约定可用性目标；未单独约定时服务按现状提供',
    ],
    'data_export' => [
        'label' => '数据导出',
        'tip' => '导出 License、审计日志、客户数据为 CSV 或 JSON 格式',
    ],
    'trial_management' => [
        'label' => '试用管理',
        'tip' => 'Trial 试用授权管理，支持限制/裁剪/到期停用/一键转正',
    ],
    'dedicated_csm' => [
        'label' => '专属客户经理',
        'tip' => '配备专属客户成功经理，提供一对一技术支持',
    ],
    'private_deploy' => [
        'label' => '私有化部署',
        'tip' => '支持在您自己的服务器上部署，数据完全私有化',
    ],
];
