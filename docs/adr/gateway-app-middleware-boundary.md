# M0-11 / M1.4-58 架构决策记录 (ADR)

## 网关 vs 应用层中间件职责边界

### 状态
✅ 已实施

### 决策日期
2026-06-05

### 背景
本平台提供 License 管理 SaaS 服务，需在 API 网关（Kong/APISIX）与应用层（Laravel）之间明确中间件的职责边界，避免双重限流、响应头冲突等问题。

### 决策

```
┌─────────────────────────────────────────────────────────┐
│                    网关层 (Kong/APISIX)                    │
├─────────────────────────────────────────────────────────┤
│  ✅ 全局限流（按 IP/全局硬限制）                             │
│  ✅ IP 黑名单/白名单                                       │
│  ✅ CC 防护（DDoS 缓解）                                    │
│  ✅ SSL 终止                                               │
│  ✅ 认证卸载（验证 JWT/API Key 有效性）                      │
│  ✅ 日志采集（访问日志）                                     │
└─────────────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────┐
│                   应用层 (Laravel 中间件)                   │
├─────────────────────────────────────────────────────────┤
│  ✅ 按租户/API 分级业务限流 (EnhancedThrottleMiddleware)    │
│  ✅ 熔断降级 (CircuitBreakerMiddleware)                    │
│  ✅ 安全响应头（CORS/CSP/HSTS/X-Frame-Options 等）         │
│  ✅ 数据脱敏                                              │
│  ✅ 幂等性保证 (IdempotencyMiddleware)                     │
│  ✅ 防暴力破解 (BruteForceMiddleware)                      │
│  ✅ MFA 多因子认证 (MfaMiddleware)                          │
└─────────────────────────────────────────────────────────┘
```

### 关键原则

1. **不冲突**：网关层和应用层可以同时设置限流，但目的不同
   - 网关层：全局硬限制（防止 DDoS/CC 攻击）
   - 应用层：精细化业务限流（按租户/API 分级）

2. **不重复**：安全响应头（CORS/CSP/HSTS）仅由应用层设置
   - 网关层不应设置这些头，避免冲突

3. **不遗漏**：所有中间件职责归属清晰

### 已实施项

#### 新增/修改文件

| 文件 | 说明 |
|------|------|
| `config/security.php` | CORS 配置 + 职责边界清单 + CSP 策略 |
| `app/Http/Middleware/SecurityHeadersMiddleware.php` | 安全响应头中间件（CORS/CSP/HSTS 等） |
| `bootstrap/app.php` | 注册 `security-headers` 别名并加入 `api` 组 |
| `app/Http/Middleware/RateLimitMiddleware.php` | 更新文档注释，明确网关/应用层边界 |
| `app/Http/Middleware/EnhancedThrottleMiddleware.php` | 更新文档注释，明确网关/应用层边界 |
| `tests/Feature/GatewayAppIntegrationTest.php` | 9 项集成测试验证两层级不冲突 |

#### SecurityHeadersMiddleware 设置的响应头

| 响应头 | 值 | 说明 |
|--------|-----|------|
| Access-Control-Allow-Origin | 动态（基于 origin） | CORS |
| Access-Control-Allow-Methods | GET, POST, PUT... | CORS 方法 |
| Access-Control-Allow-Headers | Content-Type, Authorization... | CORS 头 |
| Access-Control-Expose-Headers | X-RateLimit-* | 暴露限流头 |
| Access-Control-Allow-Credentials | true | 凭据支持 |
| Access-Control-Max-Age | 86400 | 预检缓存 |
| Strict-Transport-Security | max-age=31536000; includeSubDomains; preload | HSTS（仅 HTTPS） |
| Content-Security-Policy | 可配置 | CSP |
| X-Frame-Options | DENY | 防点击劫持 |
| X-Content-Type-Options | nosniff | 防 MIME 嗅探 |
| Referrer-Policy | strict-origin-when-cross-origin | 引用策略 |
| Permissions-Policy | camera=(), microphone=()... | 浏览器 API 权限 |
| Cache-Control | no-store, no-cache, must-revalidate | API 默认不缓存 |
| X-Request-Id | UUID | 链路追踪 |

### 测试结果

- 449 个测试全部通过
- `GatewayAppIntegrationTest` 包含 9 个集成测试覆盖：
  - 安全响应头存在性
  - CORS 预检请求
  - OPTIONS 请求无重复头
  - CSP 策略正确性
  - X-Request-Id 链路追踪一致性
  - 限流头无重复
  - 非 API 路由不影响
  - 404 错误处理
