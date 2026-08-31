# Meilisearch 部署与同步（D-34）

## 环境变量

```env
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_API_KEY=huwutong-dev-master-key
```

生产环境请使用强随机 `MEILISEARCH_API_KEY`，并与 Docker `MEILI_MASTER_KEY` 保持一致。

## 启动方式

### Windows（本地 exe，无需 Docker）

1. 从 [Meilisearch Releases](https://github.com/meilisearch/meilisearch/releases) 下载 Windows 版
2. 解压 `meilisearch.exe` 到 `%USERPROFILE%\.meilisearch\`
3. 启动：

```powershell
powershell -ExecutionPolicy Bypass -File scripts/start-meilisearch.ps1
```

### Docker（推荐 Linux/CI）

```bash
docker compose -f deploy/meilisearch/docker-compose.yml up -d
```

或使用完整开发栈：

```bash
docker compose -f deploy/docker/docker-compose.pgsql.yml up -d meilisearch
```

## 健康检查

```bash
curl http://127.0.0.1:7700/health
# {"status":"available"}
```

管理后台 API：

```http
GET /api/meilisearch/health
Authorization: Bearer {token}
```

## 全量同步

```bash
# 推荐：Artisan 命令（自动初始化索引 + 同步）
php artisan meilisearch:sync

# 仅初始化索引
php artisan meilisearch:sync --setup-only

# 同步单个索引
php artisan meilisearch:sync --type=products

# 删除并重建全部索引后同步（D-19）
php artisan meilisearch:sync --rebuild

# 兼容脚本
php scripts/sync-meilisearch.php
```

## 管理页

后台 → **Meilisearch 全文搜索**：

- 「初始化商品/知识库/…」：创建索引并设置 searchable/filterable 属性
- 「同步全部」：调用 `POST /api/meilisearch/sync`，等价于 `meilisearch:sync`

## 索引列表

| 配置键 | 默认 UID | 数据来源 |
|--------|----------|----------|
| products | products | 商品 |
| kb_articles | kb_articles | 知识库 |
| marketplace_apps | marketplace_apps | 应用市场 |
| forum_posts | forum_posts | 社区帖子 |
| blog_posts | blog_posts | 博客 |
| oa_articles | oa_articles | 互物号文章 |
| users | users | 用户 |
| official_accounts | official_accounts | 互物号账号 |

## 验收（D-34）

1. `GET /health` 或管理页 health 返回 `available`
2. `php artisan meilisearch:sync` 成功后，商品/知识库 `in_meili` > 0
3. 管理页「同步全部」可正常完成

## 商品/KB 搜索引擎（D-35）

```env
PRODUCT_SEARCH_ENGINE=meilisearch
```

- `ProductSearchService` / 门户 `/api/skus`：Meili 检索 product_id → 加载 SKU，不可用时降级 MySQL LIKE
- `GlobalSearchBar`：商品建议与 live 搜索走 Meili（其余实体仍用 search_indexes）
- `KnowledgeBaseService`：知识库搜索走 Meili，不可用时降级
- 公开 `/api/meilisearch/unified-search`、`/suggest`：Meili 不可用时返回空结果 + `meilisearch_available: false`

## 增量同步（D-36）

模型 **CUD** 后自动 upsert/delete 对应 Meilisearch 文档（默认开启）：

| 模型 | 索引 |
|------|------|
| Product | products |
| KbArticle | kb_articles |
| MarketplaceApp | marketplace_apps |
| ForumPost | forum_posts（仅 published） |
| BlogPost | blog_posts（仅 is_published） |
| OaArticle | oa_articles（仅 published） |
| User | users（仅 active） |
| OfficialAccount | official_accounts（仅 active） |

```env
MEILISEARCH_OBSERVER_ENABLED=true
MEILISEARCH_SYNC_QUEUE=false   # true 则走队列异步（≤1min 内可搜到）
```

PHPUnit 默认关闭 Observer（`MEILISEARCH_OBSERVER_ENABLED=false`），避免全量测试触发索引写入。

## 运维降级（D-19）

### 健康检查增强

`GET /api/meilisearch/health` 在不可用时返回启动提示：

- `host`、`message`、`hint`
- `start_commands.windows` / `start_commands.docker`
- `rebuild_command`

### 重建索引

```bash
# Artisan：删除全部索引 → 重建配置 → 全量同步
php artisan meilisearch:sync --rebuild

# Windows 脚本（含自动启动检测）
powershell -ExecutionPolicy Bypass -File scripts/rebuild-meilisearch.ps1

# 管理后台 API
POST /api/meilisearch/rebuild
```

### UI 降级提示

| 位置 | 行为 |
|------|------|
| 管理页 Meilisearch | 未连接时显示启动命令与重建指引 |
| 全局搜索 `GlobalSearchBar` | Meili 不可用时显示黄色警告条 |
| `GET /api/admin/search/engine-status` | 返回 `degraded: true` 与说明文案 |

搜索 API 响应附带 `engine` 字段：`meilisearch_available`、`degraded`、`message`。

### 验收（D-19）

1. 停止 Meilisearch 后，health 返回 `unavailable` + 启动提示
2. 全局搜索仍可用（降级 MySQL / search_indexes），UI 显示警告
3. `--rebuild` 或管理页「重建全部索引」可恢复索引

## 故障排查

| 现象 | 处理 |
|------|------|
| `Meilisearch 不可用` | 确认服务已启动、`.env` 中 `MEILISEARCH_HOST` 正确 |
| 401 / invalid API key | `MEILISEARCH_API_KEY` 与启动时的 master key 一致 |
| 索引为空 | 先 `--setup-only`，再 `meilisearch:sync` |
| Docker 未安装 | Windows 使用 `scripts/start-meilisearch.ps1` |
