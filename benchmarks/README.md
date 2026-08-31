# T-24 5000 QPS 压测达标验证

## 环境

- PHP 8.3 + Laravel 11
- PostgreSQL 16 (pgvector) + Redis 7 (cache/session/queue)
- 两种运行模式:
  - **PHP-FPM** (默认): `max_children=500`, 理论 ~3000 QPS
  - **Octane (Swoole)** (推荐): 16 workers, 理论 ~10000+ QPS

## 快速启动

### Octane Swoole 模式 (目标 5000 QPS)

```bash
# 启动环境
docker compose -f deploy/benchmark/docker-compose.benchmark.yml \
  --profile octane up -d --build

# 运行 5000 QPS 压测
docker compose -f deploy/benchmark/docker-compose.benchmark.yml \
  --profile k6 run --rm k6-qps
```

### PHP-FPM 模式

```bash
docker compose -f deploy/benchmark/docker-compose.benchmark.yml \
  --profile fpm up -d --build

# 运行压测
docker compose -f deploy/benchmark/docker-compose.benchmark.yml \
  --profile k6 run --rm k6-qps
```

## 配置优化

| 配置 | PHP-FPM | Octane (Swoole) |
|------|---------|-----------------|
| 进程模型 | 每个请求一个进程 | 常驻内存 Worker |
| Worker 数 | max_children=500 | 16 workers (每 worker 持续服务) |
| Opcache | 512MB / 80000 files / JIT | 同上 |
| Nginx keepalive | 32 | 256 |

## 压测脚本

| 脚本 | 类型 | 用途 |
|------|------|------|
| `benchmarks/k6/scripts/qps-target.js` | constant-arrival-rate | 5000 QPS 达标验证 |
| `benchmarks/k6/scripts/load-test.js` | ramping-vus | 混合 API 负载测试 |
| `benchmarks/k6/scripts/stress-test.js` | ramping-vus | 压力测试 |

## 结果

压测结果输出到 `benchmarks/results/k6-qps-summary.json`
