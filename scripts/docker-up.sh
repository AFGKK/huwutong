#!/usr/bin/env bash
# D-20: Docker Compose 一键启动开发栈
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "=== HWT Docker 开发栈 (D-20) ==="

if ! command -v docker >/dev/null 2>&1; then
    echo "❌ Docker 未安装"
    exit 1
fi

if [ ! -f .env ]; then
    echo "📝 创建 .env ..."
    cp .env.example .env
    php artisan key:generate --force
fi

echo "🐳 启动服务..."
docker compose up -d --build

echo ""
echo "⏳ 等待健康检查..."
sleep 8
docker compose ps

cat <<'EOF'

=== 服务地址（宿主机 PHP 使用 127.0.0.1）===
  PostgreSQL:   127.0.0.1:5432
  Redis:        127.0.0.1:6379
  Meilisearch:  http://127.0.0.1:7700
  Ollama:       http://127.0.0.1:11434
  Reverb WS:    ws://127.0.0.1:8080
  Mailpit UI:   http://127.0.0.1:8025

后续:
  php artisan migrate --seed
  php artisan meilisearch:sync
  php artisan serve --host=0.0.0.0 --port=8000
  npm run dev

详见 docs/docker-compose-dev.md
EOF
