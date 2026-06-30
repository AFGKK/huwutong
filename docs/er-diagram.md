# 数据库 ER 图与架构设计文档

> **文档版本**: 1.0 | **对应任务**: M1.4-13 | **最后更新**: 2026-06-16

---

## 1. 数据库总览

| 数据库 | 版本 | 用途 |
|:------|:---:|:----|
| MySQL 8.0 | 主数据库 | 业务数据：License/客户/设备/订单/订阅等 |
| Redis 7.0 | 缓存/队列 | Session/Cache/Queue/Rate Limit/分布式锁 |
| PostgreSQL + pgvector | AI 向量库 | 知识库 Embedding/语义搜索（M2-41a） |

### ER 图例

```
[表名]                    ← 方括号表示表
├── PK: id (BIGINT)       ← 主键
├── FK: tenant_id         ← 外键（租户隔离）
├── UK: license_key       ← 唯一索引
├── IX: status            ← 普通索引
└── 字段名: 类型           ← 字段定义

-- 关系符号 --
1──<  一对多
<──>  多对多
|──|  一对一
```

---

## 2. 核心业务 ER 图

### 2.1 租户与用户体系

```
┌─────────────────────────────────────────────────────────────────┐
│                        租户与用户体系                             │
└─────────────────────────────────────────────────────────────────┘

[tenants]                           [users]
├── PK: id (BIGINT)                 ├── PK: id (BIGINT)
├── name: VARCHAR(255)              ├── name: VARCHAR(255)
├── slug: VARCHAR(100) UK           ├── email: VARCHAR(255) UK
├── logo_url: TEXT                  ├── phone: VARCHAR(50)
├── domain: VARCHAR(255)            ├── password_hash: VARCHAR(255)
├── plan: ENUM(free,pro,enterprise) ├── tenant_id FK ──────────┐
├── status: ENUM(active,suspended)  ├── avatar_url: TEXT        │
├── data_region: VARCHAR(50)        ├── email_verified_at: DATETIME│
├── brand_color: VARCHAR(7)         └── ...                     │
└── ...                                                        │
      │                               ┌────────────────────────┘
      │                               │
      ▼                               ▼
[tenant_members]              [user_auth_providers]
├── PK: id (BIGINT)           ├── PK: id (BIGINT)
├── FK: tenant_id             ├── FK: user_id ────────────>
├── FK: user_id ──────────>   ├── provider: ENUM(email,phone,
├── role: ENUM(admin,finance, │        wechat,qq,alipay,google,
          developer,readonly)  │        github,apple)
├── invited_by: FK            ├── provider_id: VARCHAR(255)
├── joined_at: DATETIME       ├── provider_data: JSON
├── status: ENUM(active,...)  └── ...
└── ...
```

### 2.2 产品与 Feature Flag

```
[products]                      [feature_flags]
├── PK: id (BIGINT)             ├── PK: id (BIGINT)
├── name: VARCHAR(255)          ├── name: VARCHAR(255)
├── slug: VARCHAR(100) UK       ├── slug: VARCHAR(100) UK
├── description: TEXT            ├── description: TEXT
├── version: VARCHAR(20)        ├── default_value: BOOLEAN
├── is_active: BOOLEAN          ├── category: VARCHAR(50)
├── is_sellable: BOOLEAN        └── ...
├── base_price: DECIMAL(10,2)
├── sales_count: INT                   │
└── ...                                │
      │                                │
      │  ┌─────────────────────────────┤
      │  │                             │
      ▼  ▼                             ▼
[product_skus]                 [product_feature_flag]
├── PK: id (BIGINT)            ├── PK: id (BIGINT)
├── FK: product_id ───────>    ├── FK: product_id ───────>
├── name: VARCHAR(255)         ├── FK: feature_flag_id ──>
├── billing_cycle: ENUM(month, ├── value: BOOLEAN
          year,lifetime)       └── ...
├── price: DECIMAL(10,2)
├── stock: INT
├── is_active: BOOLEAN
└── ...
```

### 2.3 License 与设备管理

```
                      ┌─────────────────────────────────────┐
                      │       License 8状态转移矩阵           │
                      │                                      │
                      │  pending → active → suspended        │
                      │     ↓        ↓         ↓             │
                      │   expired  revoked  frozen           │
                      │     ↓        ↓                       │
                      │   [end]   refunded                   │
                      │             ↓                        │
                      │         blacklisted                  │
                      └─────────────────────────────────────┘

[licenses]                      [license_activations]
├── PK: id (BIGINT)             ├── PK: id (BIGINT)
├── key: VARCHAR(255) UK        ├── FK: license_id ──────>
├── type: ENUM(trial,standard,  ├── FK: device_id ───────>
          professional,enterprise)├── activated_at: DATETIME
├── status: ENUM(pending,active,├── ip_address: VARCHAR(45)
          suspended,frozen,      ├── user_agent: TEXT
          expired,revoked,       └── ...
          refunded,blacklisted)
├── FK: tenant_id               [devices]
├── FK: product_sku_id ────>    ├── PK: id (BIGINT)
├── seats: INT                  ├── fingerprint: VARCHAR(255) UK
├── valid_from: DATETIME        ├── trust_score: TINYINT
├── valid_until: DATETIME       ├── platform: VARCHAR(50)
├── max_devices: INT            ├── is_blacklisted: BOOLEAN
├── metadata: JSON              ├── FK: tenant_id
└── ...                         ├── last_seen_at: DATETIME
      │                         └── ...
      │
      ▼
[license_notes]                 [license_snapshots]
├── PK: id (BIGINT)             ├── PK: id (BIGINT)
├── FK: license_id              ├── FK: license_id
├── FK: user_id                 ├── snapshot_data: JSON
├── content: TEXT               ├── created_at: DATETIME
├── mentioned_user_id: FK       └── ...
└── ...
```

### 2.4 订阅与计费

```
[subscriptions]                 [invoices]
├── PK: id (BIGINT)             ├── PK: id (BIGINT)
├── FK: tenant_id               ├── FK: subscription_id ──>
├── FK: license_id ─────────>   ├── FK: tenant_id
├── FK: product_sku_id ────>    ├── number: VARCHAR(50) UK
├── status: ENUM(active,past_   ├── total: DECIMAL(10,2)
          due,canceled,expired) ├── status: ENUM(pending,paid,
├── current_period_start: DATE  │           canceled,refunded)
├── current_period_end: DATE    ├── tax_amount: DECIMAL(10,2)
├── auto_renew: BOOLEAN         ├── invoice_lines: JSON
├── gateway_id: VARCHAR(255)    └── ...
├── gateway_subscription_id     [tax_rates]
└── ...                         ├── PK: id (BIGINT)
      │                         ├── country: VARCHAR(2)
      ▼                         ├── rate: DECIMAL(5,4)
[renewal_attempts]              ├── type: ENUM(vat,gst,sales_tax)
├── PK: id (BIGINT)             └── ...
├── FK: subscription_id
├── attempt: TINYINT
├── status: ENUM(pending,success,failed)
├── gateway_response: JSON
└── ...
```

### 2.5 电商订单

```
[orders]                        [order_items]
├── PK: id (BIGINT)             ├── PK: id (BIGINT)
├── FK: user_id                 ├── FK: order_id ────────>
├── order_no: VARCHAR(50) UK    ├── FK: sku_id ─────────>
├── total_amount: DECIMAL(10,2) ├── FK: product_id
├── status: ENUM(pending,paid,  ├── price: DECIMAL(10,2)
          canceled,refunded)    ├── quantity: INT
├── paid_at: DATETIME           ├── FK: license_id (post-paid)
├── FK: tenant_id               └── ...
└── ...                               │
      │                               ▼
      ▼                         [deliveries]
[payments]                      ├── PK: id (BIGINT)
├── PK: id (BIGINT)             ├── FK: order_item_id
├── FK: order_id                ├── delivery_type: ENUM(license_key,
├── channel: ENUM(wechat,alipay,│         download_link,activation_code,
          stripe,paypal)                 api_key,file_package)
├── transaction_id: VARCHAR(255)├── content: TEXT
├── amount: DECIMAL(10,2)       ├── sent_at: DATETIME
├── status: ENUM(pending,       ├── delivery_channel: ENUM(
          success,failed,refunded)     email,webhook,api)
├── paid_at: DATETIME           └── ...
├── refund_at: DATETIME              │
└── ...                             │
      │                             ▼
      ▼                      [delivery_logs]
[coupons]                     ├── PK: id (BIGINT)
├── PK: id (BIGINT)           ├── FK: delivery_id
├── code: VARCHAR(50) UK      ├── channel: VARCHAR(50)
├── type: ENUM(fixed,percent) ├── status: ENUM(sent,failed)
├── value: DECIMAL(10,2)      └── ...
├── min_amount: DECIMAL(10,2)
├── usage_limit: INT                [coupon_redemptions]
├── expires_at: DATETIME       ├── PK: id (BIGINT)
└── ...                        ├── FK: coupon_id
                               ├── FK: order_id
                               ├── FK: user_id
                               └── ...
```

### 2.6 收益与佣金

```
[earnings_accounts]             [commissions]
├── PK: id (BIGINT)             ├── PK: id (BIGINT)
├── FK: user_id                 ├── FK: earnings_account_id ──>
├── FK: tenant_id               ├── FK: order_id
├── pending_balance: DECIMAL    ├── amount: DECIMAL(10,2)
├── available_balance: DECIMAL  ├── rate: DECIMAL(5,4)
├── total_withdrawn: DECIMAL    ├── status: ENUM(pending,settled,
├── frozen_amount: DECIMAL               charged_back,canceled)
├── status: ENUM(active,frozen) ├── settle_date: DATE
└── ...                         ├── freeze_until: DATE
      │                         └── ...
      │
      ▼
[withdrawals]
├── PK: id (BIGINT)
├── FK: earnings_account_id
├── amount: DECIMAL(10,2)
├── channel: ENUM(bank,alipay,wechat,paypal)
├── status: ENUM(pending,processing,paid,rejected)
├── review_note: TEXT
└── ...
```

### 2.7 安全与审计

```
[logs]                          [audit_chain_anchors]
├── PK: id (BIGINT)             ├── PK: id (BIGINT)
├── FK: tenant_id               ├── merkle_root: VARCHAR(64)
├── user_id: BIGINT             ├── prev_hash: VARCHAR(64)
├── action: VARCHAR(100)        ├── block_height: INT
├── resource_type: VARCHAR(50)  ├── anchored_at: DATETIME
├── resource_id: BIGINT         └── ...
├── old_values: JSON
├── new_values: JSON            [mfa_devices]
├── ip_address: VARCHAR(45)     ├── PK: id (BIGINT)
├── user_agent: TEXT            ├── FK: user_id
├── merkle_hash: VARCHAR(64)    ├── type: ENUM(totp,sms,email)
└── created_at: DATETIME(6)     ├── secret: TEXT
      │                         ├── is_primary: BOOLEAN
      │                         ├── last_used_at: DATETIME
      ▼                         └── ...
[webhook_events]
├── PK: id (BIGINT)             [password_policy_configs]
├── type: VARCHAR(100)          ├── PK: id (BIGINT)
├── FK: tenant_id               ├── min_length: TINYINT
├── payload: JSON               ├── require_uppercase: BOOLEAN
├── status: ENUM(pending,       ├── require_number: BOOLEAN
          delivered,failed)      ├── require_symbol: BOOLEAN
├── created_at: DATETIME        ├── max_age_days: INT
└── ...                         ├── history_limit: TINYINT
      │                         ├── lockout_threshold: TINYINT
      ▼                         ├── lockout_minutes: INT
[event_deliveries]              └── ...
├── PK: id (BIGINT)
├── FK: webhook_event_id
├── FK: webhook_endpoint_id
├── status: ENUM(pending,success,failed)
├── attempt: TINYINT
├── response_status: INT
├── response_body: TEXT
└── next_retry_at: DATETIME
```

### 2.8 Webhook 与通知

```
[webhook_endpoints]             [notifications]
├── PK: id (BIGINT)             ├── PK: id (BIGINT)
├── FK: tenant_id               ├── FK: user_id
├── url: VARCHAR(500)           ├── type: VARCHAR(100)
├── secret: VARCHAR(255)        ├── title: VARCHAR(255)
├── events: JSON                ├── body: TEXT
├── status: ENUM(active,paused) ├── data: JSON
├── max_retries: TINYINT        ├── read_at: DATETIME
├── timeout_seconds: TINYINT    └── ...
└── ...                              │
      │                              ▼
      ▼                      [notification_preferences]
[webhook_replays]             ├── PK: id (BIGINT)
├── PK: id (BIGINT)           ├── FK: user_id
├── FK: webhook_event_id      ├── channel: ENUM(mail,sms,
├── replayed_at: DATETIME            websocket,im)
├── replayed_by: BIGINT       ├── event_type: VARCHAR(100)
├── response_status: INT      ├── enabled: BOOLEAN
├── response_body: TEXT       └── ...
└── ...
```

---

## 3. 索引设计策略

### 3.1 核心查询索引

```sql
-- License 表核心索引
CREATE UNIQUE INDEX idx_licenses_key ON licenses(`key`);
CREATE INDEX idx_licenses_tenant_status ON licenses(tenant_id, status);
CREATE INDEX idx_licenses_expires ON licenses(valid_until) WHERE status = 'active';
CREATE INDEX idx_licenses_type ON licenses(type);

-- 设备表核心索引
CREATE UNIQUE INDEX idx_devices_fingerprint ON devices(fingerprint);
CREATE INDEX idx_devices_tenant_trust ON devices(tenant_id, trust_score);

-- 激活记录索引
CREATE INDEX idx_activations_license_device ON license_activations(license_id, device_id);
CREATE INDEX idx_activations_time ON license_activations(activated_at);

-- 日志索引
CREATE INDEX idx_logs_tenant_action ON logs(tenant_id, action);
CREATE INDEX idx_logs_created ON logs(created_at);

-- 订单索引
CREATE INDEX idx_orders_user_status ON orders(user_id, status);
CREATE INDEX idx_orders_tenant ON orders(tenant_id);
CREATE INDEX idx_orders_created ON orders(created_at);

-- 支付索引
CREATE UNIQUE INDEX idx_payments_transaction ON payments(transaction_id, channel);
CREATE INDEX idx_payments_order ON payments(order_id);
```

### 3.2 复合索引覆盖场景

| 索引 | 覆盖查询模式 |
|:----|:-----------|
| `licenses(tenant_id, status)` | 租户内按状态筛选 License |
| `licenses(tenant_id, valid_until)` | 租户内即将过期的 License |
| `devices(tenant_id, trust_score)` | 租户内低信任设备检测 |
| `logs(tenant_id, action, created_at)` | 租户内操作审计查询 |
| `orders(tenant_id, status, created_at)` | 租户内订单列表 |

---

## 4. 数据关系总览

```
tenants ──1──< users
tenants ──1──< customers
tenants ──1──< licenses
tenants ──1──< devices
tenants ──1──< subscriptions
tenants ──1──< invoices
tenants ──1──< orders
tenants ──1──< logs
tenants ──1──< tenant_members

users ──1──< user_auth_providers
users ──1──< notifications
users ──1──< notification_preferences
users ──1──< sessions
users ──1──< trusted_devices

licenses ──1──< license_activations
licenses ──1──< license_notes
licenses ──1──< license_snapshots
licenses ──1──< license_change_approvals

products ──1──< product_skus
products ──<──> feature_flags (pivot: product_feature_flag)

product_skus ──1──< licenses
product_skus ──1──< subscriptions

orders ──1──< order_items
orders ──1──< payments
orders ──1──< coupons_redemptions

order_items ──1──< deliveries
deliveries ──1──< delivery_logs

earnings_accounts ──1──< commissions
earnings_accounts ──1──< withdrawals

webhook_events ──1──< event_deliveries
webhook_events ──1──< webhook_replays
```

---

## 5. 数据库运维

### 5.1 迁移策略

```bash
# 创建迁移
sail artisan make:migration create_xxx_table

# 运行迁移
sail artisan migrate

# 回滚最后一批迁移
sail artisan migrate:rollback

# 零停机迁移（大表）
# 使用 pt-online-schema-change 避免锁表
pt-online-schema-change --alter "ADD COLUMN ..." D=huwutong,t=licenses
```

### 5.2 备份策略

| 项目 | 配置 |
|:----|:----|
| 全量备份 | 每日 03:00（cron） |
| 增量 binlog | 实时 |
| 保留周期 | 全量 30 天，binlog 7 天 |
| RTO 目标 | < 30 分钟 |
| RPO 目标 | < 5 分钟 |
| 异地备份 | 跨区域 S3/OSS 归档 |

### 5.3 读写分离

```php
// config/database.php
'mysql' => [
    'write' => [
        'host' => env('DB_HOST_WRITE', '127.0.0.1'),
    ],
    'read' => [
        'host' => [
            env('DB_HOST_READ_1', '127.0.0.1'),
            env('DB_HOST_READ_2', '127.0.0.1'),
        ],
    ],
],
```

---

> **维护者**: 架构组 | **最后更新**: 2026-06-16 | **相关文档**: `docs/index-design.md`, `docs/migration-strategy.md`
