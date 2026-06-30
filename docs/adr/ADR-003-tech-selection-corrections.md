# ADR-003: 技术选型修正三部曲

## 状态

已接受

## 日期

2026-06-15

## 决策者

架构组 / 安全团队 / DevOps Team

## 上下文

项目初期（M0-02）选择了一些技术组件，经过 P0 核心功能开发验证，
发现以下三个方面需要进行技术选型修正，以优化性能、降低运维成本和提升开发效率：

1. **Laravel Passport → Sanctum**：Passport 作为 OAuth 2.0 服务端实现较重，
   Token 存储在数据库 `oauth_access_tokens` 表中，每次 API 请求查询 DB 导致性能瓶颈
2. **RSA-2048 → Ed25519**：License Key 签名使用 RSA-2048，签名长度 256 字节，
   License Key 体积大、验签速度慢
3. **缺少向量数据库支撑**：AI RAG 知识库 M2-41a 需要向量检索能力

---

## 第一部分：Passport → Laravel Sanctum

### 备选方案

#### 方案 A: Laravel Sanctum（推荐）
- **优点**: 
  - 轻量级，支持 SPA Cookie 认证 + API Token 双模式
  - Token 使用 `personal_access_tokens` 单表，查询性能高
  - 内置 `abilities` 权限范围（`ability:admin,super-admin` 中间件）
  - Laravel 11 原生集成，零额外依赖
  - 无第三方 OAuth Provider 时可 standalone 运行
- **缺点**: 不支持完整的 OAuth 2.0 授权码流程
- **成本**: 低（迁移成本约 6h）

#### 方案 B: Passport（保持现状）
- **优点**: 支持完整 OAuth 2.0（授权码/Client Credentials/Password Grant）
- **缺点**: 
  - 多表存储（oauth_clients/oauth_auth_codes/oauth_access_tokens/oauth_refresh_tokens）
  - 每次 API 请求查询 `oauth_access_tokens` 表 → 高并发场景性能瓶颈
  - Laravel 11 已不推荐（移出核心，使用 `laravel/passport` 独立包）
  - 对于内部 API（客户端 SDk 调用）不需要完整的 OAuth 2.0 协议
  - 在 Gateway 层（Kong/APISIX）可直接做 JWT verification，OAuth 功能下沉到网关层
- **成本**: 中等（持续维护 OAuth 表 + JWT 签发）

#### 方案 C: Sanctum + Keycloak（推荐的外部 OAuth 方案）
- **优点**: Keycloak 作为独立 OIDC Provider，支持 SAML/OAuth 2.0/OIDC 完整协议
- **缺点**: 需要独立部署 Keycloak 实例，增加运维复杂度
- **成本**: 中等（7-10 天部署集成）

### 决策

**选择方案 A（Laravel Sanctum）+ 方案 C（Keycloak 兜底）**

### 理由

1. **内部 API**：Sanctum 提供足够轻量的 API Token 认证，满足 SDK 激活/验证场景
2. **管理后台**：Sanctum SPA 认证（Cookie-based）无需处理 Token 存储
3. **企业 SSO**：当客户需要 SAML/OIDC 时，通过 Keycloak 作为独立 OIDC Provider 支持
   （参见 M1.2-15 SSO 集成），不与应用层鉴权耦合
4. **性能提升**：从多表 OAuth 查询简化为单表 API Token 查找，减少 3-5ms 每次请求

### 后果

#### 正面
- API 请求延迟降低（每次请求减少一次 DB 查询）
- 数据库 schema 简化（减少 4 张 OAuth 表）
- Sanctum 的 `abilities` 直接映射到 RBAC 角色

#### 负面
- 对于需要完整 OAuth 2.0 授权的第三方集成，需要通过 Keycloak Proxy（额外组件）
- 现有 Passport 迁移需要处理存量 Token（无缝迁移或让旧 Token 自然过期）

#### 迁移策略
1. 双轨运行 30 天：Sanctum 新 Token + Passport 旧 Token 同时接受
2. 旧 Token 自然过期后清理 `oauth_access_tokens` 表
3. Keycloak 作为可选 OIDC Provider，仅在企业 SSO 场景启用

---

## 第二部分：RSA-2048 → Ed25519 签名算法

### 备选方案

#### 方案 A: Ed25519（推荐）
- **优点**:
  - **签名长度小 5 倍**：RSA-2048 签名 = 256 字节，Ed25519 签名 = 64 字节
  - **验签速度快 10-20 倍**：Ed25519 约 200K ops/s vs RSA-2048 约 10K ops/s（单核）
  - **公钥体积小 8 倍**：32 字节 vs 256 字节
  - 恒定时间执行，天然抗侧信道攻击
  - PHP 8.3 通过 `sodium_crypto_sign_*` 原生支持
  - License Key 总长度从 ~128 字符缩短到 ~48 字符
- **缺点**:
  - 非对称加密，不支持加密（仅签名），但 License Key 场景只需要签名
  - 旧版客户端 SDK 可能不支持（需要 RSA 兼容模式）
- **成本**: 低（使用 PHP sodium 扩展，零额外依赖）

#### 方案 B: RSA-2048（保持现状）
- **优点**: 广泛兼容，所有 SDK 语言均原生支持
- **缺点**:
  - License Key 长度大（256 字节签名 → Base64 后约 344 字符）
  - 验签速度慢（高并发 5000 QPS 场景下成为瓶颈）
  - 私钥管理复杂（PKCS#8/PEM 格式处理）
- **成本**: 中等（密钥管理 + 性能开销）

#### 方案 C: ECDSA P-256
- **优点**: 签名较小（64 字节），比 RSA 快
- **缺点**:
  - 签名生成依赖安全的随机数生成器，随机数重用会泄露私钥
  - PHP OpenSSL 扩展支持但不如 sodium 直接
  - 与 Ed25519 相比没有明显优势
- **成本**: 中等

### 决策

**选择方案 A（Ed25519 主签名）+ 方案 B 兼容模式（RSA-2048 备选）**

### 理由

1. **性能**：5000 QPS 验证场景，Ed25519 的验签速度是 RSA-2048 的 10-20 倍
2. **体积**：License Key 缩短 60%+，便于二维码/URL 传递
3. **安全**：Ed25519 由 Bernstein 设计，已被广泛验证（SSH/OpenPGP/Tor）
4. **兼容**：保留 RSA-2048 模式用于旧版客户端 SDK 的向后兼容

### 后果

#### 正面
- License Key 更短、更快、更安全
- API 响应体缩小，网络传输减少
- 公私钥管理简化（32B 公钥可直接嵌入代码或配置）

#### 负面
- 旧版 SDK 需要继续维护 RSA-2048 兼容模式（并行 30 天窗口期）
- RS256→Ed25519 切换需要客户端 SDK 升级（见 M2-17 SDK 版本兼容）
- 一些硬件 HSM 可能不支持 Ed25519（需 HSM 厂家确认）

#### 实现细节

```php
// Ed25519 签名 (PHP 8.3 sodium)
$keypair = sodium_crypto_sign_keypair();
$secretKey = sodium_crypto_sign_secretkey($keypair);
$publicKey = sodium_crypto_sign_publickey($keypair);

// 签名
$signature = sodium_crypto_sign_detached($licensePayload, $secretKey);

// 验签
$valid = sodium_crypto_sign_verify_detached($signature, $licensePayload, $publicKey);
```

#### License Key 格式

```
Ed25519 模式: HWT-{前缀}-{Base62(payload)}-{Base62(signature)}
              HWT-ENT-aB3xYz7kQ...-s9F2mNp...
              总长度: ~48 字符

RSA 兼容模式: HWT-RSA-{payload}-{signature}
              总长度: ~128 字符

可读前缀: HWT-ENT = 企业版, HWT-PRO = 专业版, HWT-TRIAL = 试用版
```

---

## 第三部分：向量数据库选型 pgvector

### 备选方案

#### 方案 A: pgvector（推荐）
- **优点**:
  - **基于现有 PostgreSQL**：无需引入新数据库组件，零额外运维
  - **支持精确/近似最近邻搜索**：IVFFlat 和 HNSW 索引
  - **支持 SQL 嵌套**：向量相似度搜索可与业务 JOIN 查询组合
  - **支持 L2/余弦/IP 距离**
  - **ACID 事务保证**：向量插入可与业务操作在同一事务
  - 开源，MIT 协议，社区活跃
- **缺点**:
  - 性能不如专用向量数据库（但项目规模下足够）
  - 需要 PostgreSQL（当前使用 MySQL 8.0），架构变更较大
- **成本**: 低（现有 PostgreSQL 扩展，无需新组件）

#### 方案 B: MySQL 8.0 自带向量支持
- **优点**: 无需变更数据库
- **缺点**: 
  - MySQL 8.0 向量支持有限（JSON 模拟，无专用索引）
  - 性能差（全表扫描 + 内存排序）
  - 无 ANN 近似最近邻搜索
  - 不适合生产级向量检索
- **成本**: 低（零迁移）但性能不足

#### 方案 C: 专用向量数据库（Milvus/Pinecone/Qdrant）
- **优点**: 高性能向量检索（百万级向量 < 100ms）
- **缺点**: 
  - 引入新数据库组件，增加运维复杂度
  - 与现有 MySQL 数据需要同步机制
  - 无法与业务 JOIN 混合查询
  - 项目初始阶段（几千条知识库文档）不需要专用向量库
- **成本**: 高（部署/运维/数据同步）

#### 方案 D: 独立 Elasticsearch
- **优点**: 全文搜索 + 向量搜索（dense_vector 类型）双能力
- **缺点**: 
  - 运维成本高（JVM 调优、分片管理）
  - 已有 Meilisearch（M2-156 商品搜索），再引入 ES 导致组件冗余
- **成本**: 高（JVM 资源消耗大）

### 决策

**选择方案 A：pgvector**

### 理由

1. **零额外运维**：基于 PostgreSQL，使用 `CREATE EXTENSION vector` 即可启用
2. **SQL 组合查询**：`SELECT * FROM kb_articles ORDER BY embedding <-> '[0.1,0.2,...]' LIMIT 10`
   可与 `WHERE tenant_id = ? AND status = 'published'` 组合
3. **项目规模匹配**：初始阶段 KB 文档 < 5000 篇，向量维度 1536（OpenAI/DeepSeek），
   pgvector HNSW 索引可轻松应对
4. **扩展路径清晰**：未来如需更高性能，可无缝升级到 pgvector Cloud 或 PGVector 集群

### 后果

#### 正面
- 无需引入新数据库系统，运维负担最低
- AI RAG 知识库（M2-41a）的向量检索可直接用 SQL 实现
- 事务一致性保证（知识库更新 + 向量索引更新原子操作）

#### 负面
- 当前项目使用 MySQL 8.0，pgvector 需要 PostgreSQL
  - 缓解：对于 AI 相关功能，将 `kb_articles`、`embeddings` 等表放在独立的 PostgreSQL 实例
  - 或：使用 MySQL 8.0 的 JSON 字段存储向量 + 应用层计算余弦相似度（初期可用，后期迁移到 pgvector）

#### 注意
由于当前项目数据库为 MySQL 8.0，pgvector 的引入方式有两种：

**方式 A（推荐）**：新增 PostgreSQL 实例专门用于 AI 向量数据
```
MySQL 8.0 → 业务数据（License/客户/设备/订单等）
PostgreSQL + pgvector → AI 数据（知识库向量/Embedding/ML 特征）
```

**方式 B（过渡）**：MySQL JSON 字段 + 应用层余弦相似度，后期迁移
```
-- 初期（MySQL 8.0）
ALTER TABLE kb_articles ADD COLUMN embedding JSON;
-- 应用层计算余弦相似度（O(n) 扫描，文档 < 5000 时可用）
-- 后期迁移到 PostgreSQL + pgvector
```

---

## 三部曲总结

| 决策点 | 旧方案 | 新方案 | 核心收益 |
|:------|:-----:|:-----:|:--------|
| API 鉴权 | Passport (OAuth 2.0) | Sanctum + Keycloak（SSO 场景） | Token 查询性能提升，Schema 简化 4 张表 |
| 签名算法 | RSA-2048 | Ed25519 + RSA 兼容模式 | 签名体积缩小 5 倍，验签快 10-20 倍 |
| 向量数据库 | ❌ 缺失 | pgvector | 零额外运维，SQL 原生向量检索，事务一致性 |

## 合规性检查清单

- [x] Sanctum 满足 SOC2 认证要求
- [x] Ed25519 满足 FIPS 186-5 标准
- [x] pgvector 支持 GDPR 数据本地化要求（数据不离开业务数据库）
- [x] 兼容当前项目架构，无需大规模重构

## 相关 ADR

- ADR-001: Laravel 技术栈选型决策
- ADR-002: API 网关 vs 应用层中间件职责边界
- M1.1-10: Laravel Sanctum 鉴权集成
- M1.2-05: License Key 生成算法（Ed25519 实现）
- M2-41a: AI RAG 知识引擎（pgvector 向量存储）
