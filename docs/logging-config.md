# 互物通 日志规范 + 配置管理

> **文档版本**: 1.0 | **更新日期**: 2026-06-15 | **对应任务**: M0-04

---

## 一、日志规范

### 1.1 日志通道配置

```php
// config/logging.php — 关键配置
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['daily', 'slack'],
        'ignore_exceptions' => false,
    ],

    'daily' => [
        'driver' => 'daily',
        'path' => storage_path('logs/laravel.log'),
        'level' => env('LOG_LEVEL', 'debug'),
        'days' => 30,  // 保留 30 天
    ],

    // 安全审计专用通道 (不可篡改)
    'audit' => [
        'driver' => 'daily',
        'path' => storage_path('logs/audit.log'),
        'level' => 'info',
        'days' => 365,
    ],

    // License 激活/验证专用通道
    'license' => [
        'driver' => 'daily',
        'path' => storage_path('logs/license.log'),
        'level' => 'info',
        'days' => 90,
    ],

    // 支付通道 (PCI 合规)
    'payment' => [
        'driver' => 'daily',
        'path' => storage_path('logs/payment.log'),
        'level' => 'info',
        'days' => 365,
        'permission' => 0600, // 敏感数据保护
    ],

    // Slack 告警
    'slack' => [
        'driver' => 'slack',
        'url' => env('LOG_SLACK_WEBHOOK_URL'),
        'username' => 'HWT Logger',
        'emoji' => ':boom:',
        'level' => env('LOG_ALERT_LEVEL', 'critical'),
    ],

    // 集中式日志平台 (M2-117)
    'aggregation' => [
        'driver' => 'monolog',
        'handler' => \App\Logging\LogAggregationHandler::class,
        'level' => 'warning',
    ],
],
```

### 1.2 日志级别规范

| 级别 | 用途 | 示例场景 |
|:----|:-----|:---------|
| **debug** | 开发调试信息 | SQL 查询、API 请求/响应 |
| **info** | 常规业务事件 | License 激活成功、用户登录、订单创建 |
| **notice** | 重要但正常的事件 | 订阅续期、许可证到期提醒 |
| **warning** | 潜在问题 | 限流触发、重试 3 次成功、API 降级 |
| **error** | 可恢复错误 | 支付失败(可重试)、第三方 API 超时、迁移失败 |
| **critical** | 严重错误需立即处理 | 数据库不可用、Redis 崩溃、License 验证批量失败 |
| **alert** | 需人工立即介入 | 安全事件、数据泄露、支付网关故障 |
| **emergency** | 系统不可用 | 服务器宕机、关键服务完全不可用 |

### 1.3 日志格式规范

#### 结构化 JSON 格式 (生产推荐)

```json
{
  "timestamp": "2026-06-15T10:30:00.123456Z",
  "channel": "license",
  "level": "info",
  "message": "License activated successfully",
  "context": {
    "license_id": 12345,
    "license_key": "HWT-XXX-****-XXXX",
    "device_fingerprint": "a1b2c3...",
    "customer_id": 678,
    "tenant_id": 1,
    "duration_ms": 45.2
  },
  "extra": {
    "trace_id": "abc123def456",
    "ip": "203.0.113.42",
    "user_agent": "HWT-SDK-PHP/2.1.0",
    "request_id": "req-789xyz",
    "memory_mb": 24.5
  },
  "file": "app/Services/ActivateService.php",
  "line": 156
}
```

#### 行文本格式 (开发/Staging)

```
[2026-06-15 10:30:00] local.INFO: License activated successfully
{"license_id":12345,"customer_id":678,"duration_ms":45.2}
```

### 1.4 日志字段规范

#### 必填字段

| 字段 | 类型 | 说明 | 示例 |
|:-----|:----|:-----|:-----|
| `timestamp` | ISO8601 | 事件发生时间 | `2026-06-15T10:30:00Z` |
| `level` | string | 日志级别 | `info`, `error` |
| `message` | string | 人类可读描述 | `License activated` |
| `channel` | string | 所属通道 | `license`, `payment`, `audit` |

#### 推荐上下文字段

| 字段 | 条件 | 说明 |
|:-----|:----|:-----|
| `tenant_id` | 多租户场景 | 租户隔离过滤 |
| `user_id` | 有用户上下文 | 操作用户追踪 |
| `trace_id` | 所有请求 | 调用链追踪 |
| `duration_ms` | API/Job 场景 | 性能监控 |
| `ip` | 网络请求 | 安全审计 |
| `license_key` | License 相关 | 需脱敏 (mask) |

### 1.5 敏感数据脱敏

日志中自动脱敏的字段（通过 `DataMaskingMiddleware` M1.3-24）：

| 字段 | 脱敏规则 | 示例 |
|:-----|:---------|:-----|
| `license_key` | 显示前缀4位+掩码 | `HWT-XXX-****-XXXX` |
| `email` | 显示域名 | `u***@example.com` |
| `phone` | 显示后4位 | `138****1234` |
| `ip` | 显示前3段 | `203.0.113.***` |
| `password` | 完全掩码 | `********` |
| `credit_card` | 显示后4位 | `****-****-****-4242` |
| `token` | 显示前8位 | `sk_live_abc***` |

### 1.6 日志使用指南

```php
// ✅ 正确 — 带结构化上下文
Log::info('License activated', [
    'license_id' => $license->id,
    'customer_id' => $customer->id,
    'duration_ms' => $duration,
]);

// ✅ 正确 — 使用专属通道
Log::channel('license')->warning('Activation rate threshold exceeded', [
    'threshold' => 100,
    'actual' => 150,
    'window_minutes' => 5,
]);

// ✅ 正确 — 异常记录
try {
    // ...
} catch (\Exception $e) {
    Log::error('Payment processing failed', [
        'order_id' => $order->id,
        'error' => $e->getMessage(),
        'trace_id' => $traceId,
    ]);
}

// ❌ 错误 — 字符串拼接
Log::info('License ' . $key . ' activated by ' . $user->name);

// ❌ 错误 — 记录敏感信息
Log::info('User token: ' . $plainToken);
```

### 1.7 日志保留策略

| 通道 | 保留天数 | 归档 | 最终存储 |
|:-----|:-------:|:----|:--------|
| `daily` (常规) | 30 天 | — | 自动清理 |
| `audit` | 365 天 | 90天后 → S3 Glacier | Deep Archive |
| `license` | 90 天 | 30天后 → 压缩归档 | S3 Standard |
| `payment` | 365 天 | 不可归档(合规) | 加密存储 |
| `aggregation` | 7 天 (DB) | — | Loki 长期 |

---

## 二、配置管理

### 2.1 环境变量规范

#### .env 文件结构

```ini
# ─── 应用基础 ───
APP_NAME=HWT License
APP_ENV=production          # local / staging / production
APP_DEBUG=false
APP_URL=https://api.huwutong.com

# ─── 数据库 ───
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=huwutong
DB_USERNAME=root
DB_PASSWORD=

# ─── Redis ───
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
REDIS_SENTINEL_SERVICE=hwt-sentinel  # 哨兵模式
REDIS_CLUSTER=false

# ─── 缓存 ───
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# ─── 安全 ───
APP_KEY=
APP_KEY_ED25519=              # Ed25519 签名密钥对
JWT_SECRET=
HASHIDS_SALT=

# ─── 邮件 ───
MAIL_MAILER=smtp
MAIL_HOST=smtp.aliyun.com
MAIL_PORT=465
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=noreply@huwutong.com
MAIL_FROM_NAME="HWT License"

# ─── OAuth 登录 ───
OAUTH_WECHAT_ENABLED=false
OAUTH_WECHAT_APPID=
OAUTH_WECHAT_SECRET=
OAUTH_QQ_ENABLED=false
OAUTH_APPLE_ENABLED=false
OAUTH_ALIPAY_ENABLED=false
OAUTH_GOOGLE_ENABLED=false
OAUTH_GITHUB_ENABLED=false

# ─── IM 通知 ───
IM_SLACK_ENABLED=false
IM_SLACK_WEBHOOK=
IM_DINGTALK_ENABLED=false
IM_DINGTALK_WEBHOOK=
IM_WECOM_ENABLED=false
IM_WECOM_WEBHOOK=
IM_FEISHU_ENABLED=false
IM_FEISHU_WEBHOOK=

# ─── AI ───
LLM_PROVIDER=deepseek          # deepseek / openai / claude / tongyi
DEEPSEEK_API_KEY=
DEEPSEEK_API_BASE=https://api.deepseek.com
OPENAI_API_KEY=
OPENAI_API_BASE=https://api.openai.com

# ─── 云存储 ───
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=hwt-license-prod
AWS_USE_PATH_STYLE_ENDPOINT=false
CDN_URL=https://cdn.huwutong.com

# ─── 对象存储 (中国) ───
OSS_ACCESS_KEY_ID=
OSS_ACCESS_KEY_SECRET=
OSS_BUCKET=hwt-license-cn
OSS_ENDPOINT=oss-cn-hangzhou.aliyuncs.com

# ─── 监控 ───
OTEL_ENABLED=true
OTEL_SERVICE_NAME=hwt-license
OTEL_EXPORTER_OTLP_ENDPOINT=http://otel-collector:4318

# ─── 第三方服务 ───
PAGERDUTY_API_KEY=
PAGERDUTY_SERVICE_ID=
OPSGENIE_API_KEY=
SENTRY_DSN=
SENTRY_ENVIRONMENT=production
```

### 2.2 环境文件命名

| 文件名 | 用途 | 是否提交 |
|:-------|:-----|:--------|
| `.env.example` | 环境变量模板 | ✅ 提交 |
| `.env` | 本地开发环境 | ❌ .gitignore |
| `.env.staging` | Staging 配置 | ❌ 密钥管理 |
| `.env.production` | 生产配置 | ❌ Vault/Secrets |
| `.env.testing` | 测试配置 | ✅ 可提交 |
| `.env.dusk.local` | Dusk 测试 | ❌ .gitignore |

### 2.3 配置最佳实践

```php
// ✅ 正确 — 通过 config() 读取
$timeout = config('license.validation.cache_ttl', 3600);

// ✅ 正确 — 配置文件中定义默认值
// config/license.php
return [
    'validation' => [
        'cache_ttl' => env('LICENSE_VALIDATION_CACHE_TTL', 3600),
    ],
];

// ❌ 错误 — 直接使用 env()
$timeout = env('LICENSE_VALIDATION_CACHE_TTL', 3600);

// ❌ 错误 — 硬编码
$timeout = 3600;
```

> **注意**: 生产环境应运行 `php artisan config:cache` 以提升性能。
> 使用 `config()` 而非 `env()` 可确保配置缓存生效。

### 2.4 密钥管理

| 密钥类型 | 存储位置 | 轮换周期 | 管理方式 |
|:---------|:---------|:--------:|:---------|
| `APP_KEY` | .env / Vault | 从不 | Laravel 生成 |
| Ed25519 密钥对 | Vault / HSM | 90 天 | M2-135 公钥版本管理 |
| JWT Secret | .env / Vault | 90 天 | 手动轮换 |
| API Key Secrets | Vault / DB 加密 | 180 天 | M2-78 Secret Manager |
| OAuth Client Secret | .env / Vault | 180 天 | 各平台管理后台 |
| 数据库密码 | .env / Vault / RDS | 90 天 | AWS RDS 自动轮换 |
| Redis 密码 | .env / Vault / AKS | 90 天 | 手动轮换 |

### 2.5 多环境配置优先级

```
.env.production (Vault)
       │
       ▼
.env.production (文件)
       │
       ▼
.env (本地)
       │
       ▼
config/*.php (默认值)
```

> 生产环境推荐使用 Kubernetes Secrets + Vault 而非 `.env` 文件。
> Staging 使用 GitHub Environments Secrets。
> 开发使用 `.env` 文件。

---

## 三、快速参考

### 3.1 新增配置变量步骤

```bash
# 1. 在对应 config/*.php 中定义
# 2. 在 .env.example 中添加注释说明
# 3. 在 .env 中设置实际值
# 4. 若需加密 → 添加到 Vault
# 5. 更新 CI/CD Secrets
```

### 3.2 日志排查流程

```
1. 确认问题时间段
2. 查询集中式日志平台 (M2-117) → 按 level/trace_id 过滤
3. 如无结果 → 检查 daily log 文件
4. 安全事件 → 检查 audit 通道
5. 支付问题 → 检查 payment 通道
```

---

> **维护者**: DevOps Team | **最后更新**: 2026-06-15
