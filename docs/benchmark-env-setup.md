# D-39 压测环境搭建

> Nginx + PHP-FPM + Redis + PostgreSQL，**非** `artisan serve`  
> 基线目标：**>1000 QPS**（`/api/health/live` 冒烟）

## 架构

```
k6 / benchmark-smoke
        │
        ▼
   Nginx :8088  ──fastcgi──►  PHP-FPM (OPcache, pm.max_children=80)
        │                           │
        │                           ├── PostgreSQL :5433
        │                           └── Redis :6380
        └── Queue Worker (redis)
```

与 D-20 开发栈区别：

| 项 | D-20 开发栈 | D-39 压测栈 |
|----|------------|------------|
| HTTP | 宿主机 `artisan serve` | **Nginx + PHP-FPM 容器** |
| 端口 | 8000 | **8088** |
| DB 端口 | 5432 | **5433**（避免冲突） |
| Redis | 6379 | **6380** |
| OPcache | 可选 | **强制启用** |
| APP_DEBUG | true | false |

## 快速开始

### Windows

```powershell
powershell -ExecutionPolicy Bypass -File scripts/benchmark-up.ps1

# 首次初始化
docker compose -f deploy/benchmark/docker-compose.benchmark.yml exec app php artisan migrate --seed --force

# 环境检查
docker compose -f deploy/benchmark/docker-compose.benchmark.yml exec app php artisan benchmark:env-check

# 基线冒烟（>1000 QPS）
powershell -File scripts/benchmark-smoke.ps1
```

### Linux / macOS

```bash
bash scripts/benchmark-up.sh
docker compose -f deploy/benchmark/docker-compose.benchmark.yml exec app php artisan migrate --seed --force
docker compose -f deploy/benchmark/docker-compose.benchmark.yml exec app php artisan benchmark:env-check
bash scripts/benchmark-smoke.sh
```

## k6 端到端压测

```bash
# 获取 Token 后
export TOKEN="your-api-token"
export BASE_URL="http://127.0.0.1:8088/api"

k6 run benchmarks/k6/scripts/smoke.js
k6 run -e STAGE=smoke benchmarks/k6/scripts/load-test.js

# Docker 内跑 smoke（需 --profile k6）
docker compose -f deploy/benchmark/docker-compose.benchmark.yml --profile k6 run --rm k6-smoke
```

## 环境变量

```env
# 压测栈端口（可选，避免与 D-20 冲突）
BENCH_HTTP_PORT=8088
BENCH_DB_PORT=5433
BENCH_REDIS_PORT=6380
BENCHMARK_RUNTIME=nginx-php-fpm
BENCHMARK_TOKEN=           # k6 用

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

## 调优参数

| 文件 | 说明 |
|------|------|
| `deploy/benchmark/php/www.conf` | PHP-FPM 进程池（max_children=80） |
| `deploy/benchmark/php/opcache.ini` | OPcache + JIT |
| `deploy/benchmark/nginx.benchmark.conf` | keepalive、fastcgi 缓冲 |

## 验收（D-39）

1. `docker compose -f deploy/benchmark/docker-compose.benchmark.yml ps` 全部 running/healthy
2. `curl http://127.0.0.1:8088/api/health/live` → 200
3. `php artisan benchmark:env-check`（在 app 容器内）→ 全部 ✅
4. `benchmark-smoke.ps1` QPS ≥ 1000
5. **未使用** `artisan serve` 作为 HTTP 入口

## 故障排查

| 现象 | 处理 |
|------|------|
| 8088 连接拒绝 | `docker compose -f deploy/benchmark/docker-compose.benchmark.yml logs nginx app` |
| 502 Bad Gateway | 等待 app 容器 composer install 完成；检查 `vendor/` |
| QPS 低于 1000 | 增大 `pm.max_children`；确认 OPcache 已启用 |
| 端口冲突 | 修改 `BENCH_*_PORT` |
| k6 401 | 设置 `BENCHMARK_TOKEN` 或先跑 health 端点 |

## 相关任务

- **D-20**：开发 Docker 栈（可并行运行，端口不同）
- **D-40**：5000 QPS 达标验证 — **已归档** `benchmarks/results/benchmark-result.json`（本机 artisan serve 0.4 QPS 未达标；需本栈 + k6 复测）
- **T-24**：k6 smoke→load→stress — **已归档**（k6 未安装，HTTP 层未达标）
