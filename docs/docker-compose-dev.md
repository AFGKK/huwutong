# Docker Compose 一键开发栈（D-20）

## 概述

一条命令启动本地开发所需的全部基础设施：

| 服务 | 容器名 | 宿主机端口 | 用途 |
|------|--------|-----------|------|
| PostgreSQL 16 + pgvector | hwt-postgres | 5432 | 主数据库 |
| Redis 7 | hwt-redis | 6379 | 缓存 / 队列 |
| Meilisearch | hwt-meilisearch | 7700 | 全文搜索 |
| Ollama | hwt-ollama | 11434 | 本地 LLM |
| Reverb | hwt-reverb | 8080 | WebSocket (IM/通话) |
| Queue Worker | hwt-queue | — | Redis 队列消费 |
| Mailpit | hwt-mailpit | 1025 / 8025 | 开发邮件捕获 |

可选 MySQL 8.4（`--profile mysql`）。

## 快速开始

### Windows

```powershell
powershell -ExecutionPolicy Bypass -File scripts/docker-up.ps1
```

### Linux / macOS

```bash
bash scripts/docker-up.sh
# 或完整初始化（含 migrate/seed）
bash scripts/dev-start.sh
```

### 手动

```bash
docker compose up -d --build
docker compose ps
```

## 宿主机 .env 配置

Docker 栈启动后，**宿主机**上运行的 `php artisan serve` 应使用 `127.0.0.1`：

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=huwutong
DB_USERNAME=postgres
DB_PASSWORD=postgres

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
QUEUE_CONNECTION=redis
CACHE_DRIVER=redis
SESSION_DRIVER=redis

MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_API_KEY=huwutong-dev-master-key

OLLAMA_API_BASE=http://127.0.0.1:11434
LOCAL_LLM_ENABLED=true

REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
VITE_REVERB_HOST=127.0.0.1
VITE_REVERB_PORT=8080

MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
```

> Reverb / Queue 容器内使用 Docker 网络服务名（`postgres`、`redis`），由 compose 环境变量注入，无需修改。

## 初始化数据

```bash
php artisan migrate --seed
php artisan meilisearch:sync
php artisan ollama:setup --pull   # 可选，拉取本地模型
```

## 启动应用

```bash
# Terminal 1
php artisan serve --host=0.0.0.0 --port=8000

# Terminal 2
npm run dev
```

Reverb 与 Queue 已在容器中运行，无需再开 `reverb:start` / `queue:work` 终端。

## MySQL 可选栈

```bash
docker compose --profile mysql up -d mysql redis meilisearch ollama reverb queue mailpit
```

并设置：

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DOCKER_DB_HOST=mysql
```

## 常用命令

```bash
# 查看日志
docker compose logs -f reverb queue

# 停止
docker compose down

# 停止并清除数据卷
docker compose down -v

# 仅启动部分服务
docker compose up -d postgres redis meilisearch
```

## 文件结构

```
docker-compose.yml              # 根入口（include dev 栈）
deploy/docker/docker-compose.dev.yml
deploy/docker/Dockerfile.cli    # Reverb / Queue 镜像
scripts/docker-up.ps1
scripts/docker-up.sh
scripts/dev-start.sh
compose.sail.yaml               # Laravel Sail 遗留（需 -f compose.sail.yaml）
```

## 验收（D-20）

1. `docker compose up -d` 全部服务 `healthy` 或 `running`
2. `curl http://127.0.0.1:7700/health` → `available`
3. `curl http://127.0.0.1:11434/api/tags` → 200
4. 宿主机 `php artisan serve` + 前端可登录；IM WebSocket 连 `ws://127.0.0.1:8080`
5. 投递队列任务可被 `hwt-queue` 容器消费

## 故障排查

| 现象 | 处理 |
|------|------|
| 端口占用 | 修改 `.env` 中 `FORWARD_*_PORT` |
| Reverb/Queue 重启循环 | 确认 `.env` 存在且 `vendor/` 已 `composer install` |
| Meilisearch 401 | `MEILISEARCH_API_KEY` 与 compose 中 `MEILI_MASTER_KEY` 一致 |
| Ollama 无模型 | `docker exec hwt-ollama ollama pull qwen2.5:7b` |
| Sail 用户 | 使用 `docker compose -f compose.sail.yaml up -d` |

## 相关任务

- **D-34 / D-37**：Meilisearch / Ollama 运行时
- **D-39**：压测环境（基于本栈扩展 Nginx + PHP-FPM）
- **T-17**：Docker 启动后跑 PHPUnit 冒烟
