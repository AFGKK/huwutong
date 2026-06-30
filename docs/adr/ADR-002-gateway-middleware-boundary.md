# ADR-002: API 网关 vs 应用层中间件职责边界

## 状态

已接受

## 日期

2026-06-15

## 决策者

架构组 / DevOps Team

## 上下文

互物通系统需要同时引入 API 网关（Kong/APISIX）和应用层中间件来保障系统安全、可观测性和流量管理。
如果不明确划分职责边界，可能导致：

1. **双重限流**：网关和 Laravel 同时限流，用户看到 `429 Too Many Requests` 但无法确定限流源
2. **响应头冲突**：网关设置 HSTS/CSP/CORS，应用层也设置，导致重复或覆盖
3. **职责碎片化**：同样功能（如 IP 黑名单）网关和应用层各维护一份，配置不同步
4. **故障定位困难**：限流/熔断触发时，无法快速判断是网关层级还是应用层级

M1.3-20（API 网关统一层）和 M1.4-58（网关-应用层职责划分 ADR 实施）依赖此决策。

## 备选方案

### 方案 A: 网关负责边缘安全，应用层负责业务安全（推荐）
- **优点**: 职责清晰、可独立扩缩、故障隔离
- **缺点**: 需要两层配置、增加运维复杂度
- **成本**: 中等（需维护两套规则，但每套规则职责单一）

### 方案 B: 全部在网关层实现
- **优点**: 单一配置点、流量全管控
- **缺点**: 无法实现业务感知的限流（按租户/API Key 等级）、Laravel 中间件生态无法复用
- **成本**: 高（网关 Lua/Plugin 开发成本高）

### 方案 C: 全部在应用层实现
- **优点**: Laravel 中间件生态丰富、开发效率高
- **缺点**: 无法在最边缘拦截 DDoS/CC 攻击、SSL 卸载仍需网关、应用层压力大
- **成本**: 低（不引入网关组件）

## 决策

**选择方案 A：网关负责边缘安全，应用层负责业务安全**

## 理由

1. **安全纵深防御**：攻击在网关层首先被过滤（IP 黑名单/CC 防护/SSL 终止），业务安全在应用层精细管控
2. **利用现有中间件生态**：已实现的 29 个中间件（SecurityHeadersMiddleware/RateLimitMiddleware/CorsManager 等）无需重写
3. **故障隔离**：网关宕机不影响业务逻辑（仅无法新建连接），应用层熔断不依赖网关
4. **可独立扩缩**：网关层可独立水平扩展应对 DDoS，应用层按业务 QPS 扩展

## 后果

### 正面
- 明确的分工边界，新开发者容易理解
- 两层级配置各自独立，互不影响
- 网关层的全局规则（IP 黑名单/全局限流）统一管理

### 负面
- 运维需维护两套配置系统
- 排错时需同时检查两层日志

### 风险
- 网关和应用层限流阈值若设置不当可能导致"双重限流"
- 缓解：应用层限流阈值 = 网关层阈值 × 0.9（预留 10% 余量）

## 职责划分矩阵

| 功能 | 归属层 | 具体实现 | 说明 |
|:----|:-----:|:--------|:----|
| SSL/TLS 终止 | 🔵 网关 | Kong SSL Plugin / Let's Encrypt | 卸载 HTTPS，以 HTTP 转发给应用 |
| DDoS/CC 防护 | 🔵 网关 | Cloudflare / ModSecurity / 按 IP 限流 | 全局速率限制，到达应用层前拦截 |
| IP 黑白名单 | 🔵 网关 | Kong IP Restriction Plugin | 全局级 IP 管控，应用层不重复设置 |
| 全局限流（全局 QPS） | 🔵 网关 | Kong Rate Limiting Plugin | 按 IP/API 的全局 QPS 限制，阈值高于应用层 |
| 认证卸载（Token 校验） | 🔵 网关 | Kong JWT/OAuth2 Plugin（可选） | 可选在网关层预检 Token 有效性，减轻应用层压力 |
| 请求日志采集 | 🔵 网关 | Kong File Log / TCP Log Plugin | 采集原始请求日志（IP/UA/Path/Status） |
| 按租户/API 分级限流 | 🟢 应用层 | `RateLimitMiddleware.php` | 按租户套餐等级设置不同 QPS 上限 |
| 熔断降级 | 🟢 应用层 | `CircuitBreakerMiddleware.php` | Redis 不可用→降级 DB，DB 不可用→熔断响应 |
| CORS 跨域策略 | 🟢 应用层 | Laravel CORS config / `CorsManager` | 按 Origin 动态配置，支持管理后台可视化 |
| CSP 内容安全策略 | 🟢 应用层 | `CspConfigController` + `SecurityHeadersMiddleware` | 管理后台可视化配置白名单域名 |
| HSTS/X-Frame/X-Content-Type | 🟢 应用层 | `SecurityHeadersMiddleware.php` | 统一设置安全响应头，后台可管理开关 |
| 数据脱敏 | 🟢 应用层 | `DataMaskingMiddleware.php` | 按角色（超管/运营/客户）控制脱敏程度 |
| 业务限流（按 API Key/用户） | 🟢 应用层 | `ApiKeyAuthMiddleware` + `FineGrainedApiKeyMiddleware` | API Key 维度精细限流 |
| 防重放 Nonce/Timestamp | 🟢 应用层 | `NonceMiddleware.php` + `SignatureMiddleware.php` | 激活 API 请求防重放保护 |
| 幂等性 | 🟢 应用层 | `IdempotencyMiddleware.php` | 支付回调/激活请求幂等处理 |
| WAF 深度检测 | 🟢 应用层 | `WafMiddleware.php` | OWASP Top 10 规则检测（应用层补充网关的不足） |
| 请求体大小限制 | 🟢 应用层 | `BodySizeLimiter.php` | 按 API 类型分级限制（激活 10KB / 上传 10MB） |
| 暴力枚举阻断 | 🟢 应用层 | `BruteForceMiddleware.php` | 连续无效 License Key → 临时封禁 IP |
| 响应头冲突预防 | 🟢 应用层 | 网关不设置安全响应头，全部由应用层统一管理 | 避免 HSTS/CSP/CORS 重复设置 |
| 维护模式 | 🟢 应用层 | `MaintenanceMiddleware.php` | 一键开启维护页 + 白名单 IP 绕过 |
| gRPC 内部通信 | 🔵 网关 | Kong gRPC Proxy / 直连 | 内部服务间 gRPC 调用走独立通道 |
| 健康检查 | 🔵 网关 | Kong Health Checks | 网关级上游健康检测 |

> 🔵 = 网关层 | 🟢 = 应用层

## 限流阈值校准规则

为避免双重限流导致正常请求被误拦，两层级限流按以下规则校准：

```text
应用层业务限流阈值 = 网关层全局限流阈值 × 0.9

示例：
  网关层：1000 QPS（按 IP 全局限流）
  应用层：900 QPS（按租户/API Key 分级限流，总和不超过 900）
          每个租户：900 ÷ 租户数（按等级加权分配）
```

当应用层触发限流时，响应头包含：
```
X-RateLimit-Layer: application
X-RateLimit-Limit: 900
X-RateLimit-Remaining: 0
```

当网关层触发限流时，响应头包含：
```
X-RateLimit-Layer: gateway
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 0
```

## 现有中间件清单（应用层）

已实现的 29 个应用层中间件，全部归属应用层职责：

| # | 中间件 | 文件 | 职责 |
|:--|:------|:----|:----|
| 1 | ActivateSecurity | `ActivateSecurityMiddleware.php` | 激活安全防护 |
| 2 | ApiKeyAuth | `ApiKeyAuthMiddleware.php` | API Key 鉴权 |
| 3 | ApiVersion | `ApiVersionMiddleware.php` | API 版本路由 |
| 4 | Apm | `ApmMiddleware.php` | APM 追踪 |
| 5 | BodySizeLimiter | `BodySizeLimiter.php` | 请求体限制 |
| 6 | BruteForce | `BruteForceMiddleware.php` | 暴力枚举阻断 |
| 7 | CircuitBreaker | `CircuitBreakerMiddleware.php` | 熔断降级 |
| 8 | DataMasking | `DataMaskingMiddleware.php` | 数据脱敏 |
| 9 | EnhancedThrottle | `EnhancedThrottleMiddleware.php` | 增强限流 |
| 10 | FineGrainedApiKey | `FineGrainedApiKeyMiddleware.php` | API Key 细粒度权限 |
| 11 | GlobalResourceWhitelist | `GlobalResourceWhitelist.php` | 全局资源白名单 |
| 12 | GlobalResourceWriteProtection | `GlobalResourceWriteProtection.php` | 全局资源写入保护 |
| 13 | Idempotency | `IdempotencyMiddleware.php` | 幂等性 |
| 14 | Impersonate | `ImpersonateMiddleware.php` | 模拟登录 |
| 15 | Maintenance | `MaintenanceMiddleware.php` | 维护模式 |
| 16 | Mfa | `MfaMiddleware.php` | MFA 验证 |
| 17 | Nonce | `NonceMiddleware.php` | 防重放 |
| 18 | RateLimit | `RateLimitMiddleware.php` | 分级限流 |
| 19 | ResolveBranding | `ResolveBranding.php` | 品牌解析 |
| 20 | ResolveDomainTenant | `ResolveDomainTenant.php` | 域名租户解析 |
| 21 | SecurityCenter | `SecurityCenterMiddleware.php` | 安全中心 |
| 22 | SecurityHeaders | `SecurityHeadersMiddleware.php` | 安全响应头 |
| 23 | SetTenantContext | `SetTenantContext.php` | 租户上下文 |
| 24 | Signature | `SignatureMiddleware.php` | 签名校验 |
| 25 | SlowQuery | `SlowQueryMiddleware.php` | 慢查询检测 |
| 26 | SmartContract | `SmartContractMiddleware.php` | 智能合约 |
| 27 | TokenIntrospection | `TokenIntrospectionMiddleware.php` | Token 内省 |
| 28 | Waf | `WafMiddleware.php` | WAF 防护 |
| 29 | WidgetAuth | `WidgetAuthMiddleware.php` | Widget 鉴权 |

## 合规性检查清单

- [x] 安全纵深防御体系完整
- [x] 无双重限流风险（已定义校准规则）
- [x] 无响应头冲突风险
- [x] 故障隔离性良好
- [x] 与现有 29 个中间件兼容

## 相关 ADR

- ADR-001: 技术栈选型决策
- ADR-003: 技术选型修正（Sanctum/Ed25519/pgvector）
- M1.4-58: 网关-应用层职责划分 ADR 实施
