#!/usr/bin/env bash
# D-39: 启动压测环境
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
COMPOSE="deploy/benchmark/docker-compose.benchmark.yml"

echo "=== D-39 压测环境搭建 ==="

if ! command -v docker >/dev/null 2>&1; then
    echo "❌ Docker 未安装"
    exit 1
fi

[ -f .env ] || { cp .env.example .env && php artisan key:generate --force; }

docker compose -f "$COMPOSE" up -d --build

echo "⏳ 等待健康检查..."
for i in $(seq 1 30); do
    if curl -sf http://127.0.0.1:8088/api/health/live >/dev/null 2>&1; then
        echo "✅ API 就绪"
        break
    fi
    sleep 3
done

docker compose -f "$COMPOSE" ps

cat <<'EOF'

=== 压测入口 ===
  HTTP:   http://127.0.0.1:8088/api
  初始化: docker compose -f deploy/benchmark/docker-compose.benchmark.yml exec app php artisan migrate --seed --force
  检查:   docker compose -f deploy/benchmark/docker-compose.benchmark.yml exec app php artisan benchmark:env-check
  冒烟:   bash scripts/benchmark-smoke.sh
EOF
