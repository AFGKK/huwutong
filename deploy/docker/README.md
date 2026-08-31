# 互物通 Docker 全栈一键启动 (D-20)

使用 Docker Compose 一键启动完整的互物通开发环境，
包含 PostgreSQL / MySQL(可选) / Redis / Meilisearch / Ollama / Reverb / Mailpit / Queue Worker / Schedule / App。

## 前提条件

- Docker Engine 24+ & Docker Compose v2
- 至少 8GB 可用内存（推荐 16GB）
- 磁盘空间：~5GB（含 Docker 镜像 + Ollama 模型 ≈ 2GB）

## 快速启动

### Windows (Cmd)

```cmd
:: 1. 复制环境变量
copy .env.example .env

:: 2. 启动全栈
docker-start.cmd up

:: 3. 查看状态
docker-start.cmd status

:: 4. 重建数据库 + 填充种子数据
docker-start.cmd fresh

:: 5. 关闭环境
docker-start.cmd down
```

### Windows (PowerShell)

```powershell
# 1. 复制环境变量
Copy-Item .env.example .env

# 2. 启动全栈
.\docker-start.ps1 up

# 3. 查看状态
.\docker-start.ps1 status

# 4. 安装依赖 + 迁移
docker compose run --rm app composer install
docker compose run --rm app php artisan key:generate
docker compose run --rm app php artisan migrate:fresh --seed

# 5. 关闭环境
.\docker-start.ps1 down
```

### Linux / macOS / WSL

```bash
# 1. 复制环境变量
cp .env.example .env

# 2. 启动全栈
docker compose up -d

# 3. 安装依赖（首次）
docker compose run --rm app composer install
docker compose run --rm app php artisan key:generate
docker compose run --rm app php artisan migrate:fresh --seed

# 4. 前端构建
docker compose run --rm app npm install && npm run build

# 5. 关闭环境
docker compose down
```

## 包含服务

| 服务 | 端口 | 说明 |
|------|:----:|:-----|
| App (Nginx + PHP 8.3) | 80 | Web 应用 |
| PostgreSQL (pgvector) | 5432 | 主数据库 |
| MySQL (可选) | 3306 | 备选数据库 |
| Redis | 6379 | 缓存/队列/会话 |
| Meilisearch | 7700 | 全文搜索引擎 |
| Reverb | 8080 | WebSocket 实时通信 |
| Ollama | 11434 | 本地 LLM |
| Mailpit | 1025 / 8025 | 邮件捕获 + 管理界面 |
| Queue Worker | — | Redis 队列消费 |
| Schedule | — | Laravel 定时任务 |

## 环境变量

在 `.env` 中可配置以下 Docker 相关变量：

```
# Docker 宿主机端口映射（可选，默认值已合理）
FORWARD_DB_PORT=5432
FORWARD_REDIS_PORT=6379
FORWARD_MEILISEARCH_PORT=7700
FORWARD_OLLAMA_PORT=11434
FORWARD_REVERB_PORT=8080
FORWARD_MAILPIT_PORT=1025
FORWARD_MAILPIT_DASHBOARD_PORT=8025

# 容器内服务主机名（宿主机访问用 127.0.0.1）
# DOCKER_DB_HOST=postgres
```

## 健康检查

```bash
# 全部容器状态
docker compose ps

# Laravel 应用
curl http://localhost/api/health

# Meilisearch
curl http://localhost:7700/health

# Ollama
curl http://localhost:11434/api/tags

# 邮件管理界面
open http://localhost:8025
```

## 常用命令

```bash
# 运行 Artisan 命令
docker compose run --rm app php artisan migrate:status

# 进入容器
docker compose exec app bash

# 查看日志
docker compose logs -f app
docker compose logs -f queue

# 重建特定服务
docker compose up -d --force-recreate ollama

# 仅启动必需服务（节约资源）
docker compose up -d postgres redis app
```

## 从旧 Sail 迁移

如果你之前在使用 `compose.sail.yaml`（Sail 版）：

```bash
# 1. 停止旧的 Sail 环境
docker compose -f compose.sail.yaml down

# 2. 删除旧 Sail 的容器和数据
docker compose -f compose.sail.yaml down -v

# 3. 使用新的全栈配置
docker compose up -d
```

## 生产部署对照

本配置针对**本地开发**优化。生产环境建议：

1. 使用 `deploy/nginx/production-https.conf` — Nginx + HTTPS + WebSocket 反代
2. 使用 `deploy/supervisor/` — Reverb / Queue Worker 进程守护
3. 使用独立 `docker-compose.yml` 部署 Meilisearch / Ollama
4. 数据库使用托管服务（RDS / Cloud SQL）
