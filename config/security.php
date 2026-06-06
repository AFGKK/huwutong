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
    */
];
