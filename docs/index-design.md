# 数据库索引设计评审

> **对应任务**: M1.1-17 | **优先级**: P0 | **日期**: 2026-06-15

## 设计原则

1. **覆盖索引优先**：核心查询路径使用覆盖索引，避免回表查询
2. **复合索引左前缀**：按查询条件选择性从高到低排列
3. **避免冗余索引**：`morphs()` 已自动创建复合索引，不重复添加
4. **外键索引自动管理**：`foreignId()->constrained()` 自动创建索引

## 核心查询模式与索引策略

### 1. License 查询

| 查询模式 | 索引策略 | 说明 |
|---------|---------|------|
| `WHERE license_key = ?` | UNIQUE INDEX `licenses_license_key_unique` | 核心查询，全表唯一 |
| `WHERE tenant_id = ? AND status = ?` | INDEX `licenses_tenant_id_status_index` | 租户维度列表查询 |
| `WHERE tenant_id = ? AND expires_at < ?` | INDEX `licenses_tenant_id_expires_at_index` | 过期扫描 |
| `WHERE tenant_id = ? AND product_id = ?` | INDEX `licenses_tenant_id_product_id_index` | 产品维度统计 |

### 2. 设备查询

| 查询模式 | 索引策略 |
|---------|---------|
| `WHERE device_fingerprint = ?` | INDEX `devices_fingerprint_index` |
| `WHERE tenant_id = ? AND trust_score < ?` | INDEX `devices_tenant_id_trust_score_index` |
| `WHERE license_id = ? AND status = ?` | INDEX `devices_license_id_status_index` |

### 3. 激活/验证查询

| 查询模式 | 索引策略 |
|---------|---------|
| `WHERE license_id = ? AND status = ?` | INDEX `license_activations_license_id_status_index` |
| `WHERE device_id = ? AND license_id = ?` | INDEX `license_activations_device_id_license_id_index` |

### 4. 租户隔离查询（全表通用）

所有包含 `tenant_id` 的表均建有 `table_tenant_id_index` 单列索引，用于跨表 JOIN 和租户级扫描。

## 索引设计规范

### 命名规范

```sql
-- 单列索引: {table}_{column}_index
INDEX `users_email_index`

-- 复合索引: {table}_{col1}_{col2}_index
INDEX `licenses_tenant_id_status_index`

-- 唯一索引: {table}_{col}_unique
UNIQUE `licenses_license_key_unique`

-- 外键索引: {table}_{column}_foreign
INDEX `devices_license_id_foreign`
```

### 冗余索引清理记录

| 日期 | 清理内容 | 涉及文件 |
|------|---------|---------|
| 2026-06-15 | 移除 `morphs()` 后重复的复合索引 | 4 个迁移文件 |
| 2026-06-15 | 移除 `constrained()` 后重复的单列索引 | 20 个迁移文件 |
| 2026-06-15 | 移除 `carts` 表重复的 `session_id` 索引 | 1 个迁移文件 |

## 验证方法

```sql
-- 检查缺失索引
SELECT t.TABLE_NAME, t.TABLE_ROWS
FROM information_schema.TABLES t
WHERE t.TABLE_SCHEMA = 'huwutong'
  AND t.ENGINE = 'InnoDB';

-- 检查未使用的索引（通过 performance_schema）
SELECT * FROM sys.schema_unused_indexes
WHERE object_schema = 'huwutong';
```
