<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CORS 配置
    |--------------------------------------------------------------------------
    |
    | CORS 由应用层 SecurityHeadersMiddleware 统一处理。
    | 网关层不应设置 CORS 头，避免冲突。
    |
    */
    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', '*')),

    /*
    |--------------------------------------------------------------------------
    | 默认签名密钥（用于 SDK 签名校验）
    |--------------------------------------------------------------------------
    |
    | 开发/测试环境下，SDK 可使用此密钥计算 HMAC 签名。
    | 生产环境应通过 License metadata 或 API Key 管理。
    |
    */
    'default_signature_secret' => env('DEFAULT_SIGNATURE_SECRET', 'huwutong-dev-secret-key-2024'),

    /*
    |--------------------------------------------------------------------------
    |--------------------------------------------------------------------------
    | 安全响应头配置
    |--------------------------------------------------------------------------
    */
    'csp_policy' => env('CSP_POLICY', "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self' *; frame-ancestors 'none'; base-uri 'self'"),

    /*
    |--------------------------------------------------------------------------
    | 网关-应用层职责边界清单
    |--------------------------------------------------------------------------
    |
    | 按 M0-11 ADR 定义：
    |
    | [网关层 — Kong/APISIX 负责]
    | 1. 全局限流（按 IP/全局，硬限制）
    | 2. IP 黑名单/白名单
    | 3. CC 防护（DDoS 缓解）
    | 4. SSL 终止
    | 5. 认证卸载（验证 JWT/API Key 有效性）
    | 6. 日志采集（访问日志）
    |
    | [应用层 — Laravel 中间件负责]
    | 1. 按租户/API 分级业务限流 (EnhancedThrottleMiddleware)
    | 2. 熔断降级 (CircuitBreakerMiddleware)
    | 3. 安全响应头（CORS/CSP/HSTS/X-Frame-Options 等）
    | 4. 数据脱敏
    | 5. 幂等性保证 (IdempotencyMiddleware)
    | 6. 防暴力破解 (BruteForceMiddleware)
    |
    | [安全事件响应 SOP (M3-25)]
    | 通报→止损→取证→修复→复盘
    */

    /*
    |--------------------------------------------------------------------------
    | 安全事件响应 SOP 配置 (M3-25)
    |--------------------------------------------------------------------------
    */
    'incident_response' => [
        'enabled' => true,
        'tiers' => [
            'critical' => [
                'label' => '严重 (P0)',
                'response_time' => 15,      // 15分钟响应
                'escalation_minutes' => 30,
                'notify_channels' => ['sms', 'phone', 'email', 'slack'],
                'auto_execute_sop' => true,
            ],
            'high' => [
                'label' => '高危 (P1)',
                'response_time' => 30,       // 30分钟响应
                'escalation_minutes' => 60,
                'notify_channels' => ['phone', 'email', 'slack'],
                'auto_execute_sop' => true,
            ],
            'medium' => [
                'label' => '中危 (P2)',
                'response_time' => 60,       // 60分钟响应
                'escalation_minutes' => 120,
                'notify_channels' => ['email', 'slack'],
                'auto_execute_sop' => false,
            ],
            'low' => [
                'label' => '低危 (P3)',
                'response_time' => 120,      // 120分钟响应
                'escalation_minutes' => 240,
                'notify_channels' => ['email'],
                'auto_execute_sop' => false,
            ],
        ],

        // SOP 5阶段流程
        'phases' => [
            'notify' => '通报: 通知安全团队和相关方',
            'contain' => '止损: 立即隔离受影响系统',
            'investigate' => '取证: 收集日志和证据',
            'remediate' => '修复: 应用补丁和恢复服务',
            'review' => '复盘: 事后分析和改进',
        ],

        // 预定义的SOP步骤动作类型
        'action_types' => [
            'log_event' => ['label' => '记录事件', 'auto' => true],
            'notify_admin' => ['label' => '通知管理员', 'auto' => true],
            'notify_user' => ['label' => '通知用户', 'auto' => false],
            'block_ip' => ['label' => '封禁IP', 'auto' => true],
            'terminate_sessions' => ['label' => '终止会话', 'auto' => true],
            'disable_account' => ['label' => '禁用账号', 'auto' => false],
            'require_mfa' => ['label' => '强制MFA', 'auto' => true],
            'send_alert_email' => ['label' => '发送告警邮件', 'auto' => true],
            'create_ticket' => ['label' => '创建工单', 'auto' => true],
            'custom_webhook' => ['label' => '自定义Webhook', 'auto' => false],
        ],

        // 通报联系人
        'contacts' => [
            'security_team_email' => env('SECURITY_TEAM_EMAIL', 'security@huwutong.com'),
            'security_team_phone' => env('SECURITY_TEAM_PHONE'),
            'slack_webhook' => env('SECURITY_SLACK_WEBHOOK'),
            'pagerduty_key' => env('PAGERDUTY_API_KEY'),
        ],
    ],
];
