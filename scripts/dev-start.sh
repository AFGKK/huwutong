#!/usr/bin/env bash
# D-20: 更新版开发环境启动（Docker 基础设施 + 本地 PHP）
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "================================================"
echo "  HWT License 一键开发环境 (D-20)"
echo "================================================"

if ! command -v docker >/dev/null 2>&1; then
    echo "❌ Docker 未安装，请先安装 Docker Desktop"
    exit 1
fi

if [ ! -f .env ]; then
    echo "📝 创建 .env ..."
    cp .env.example .env
    php artisan key:generate --force
fi

echo "🐳 启动 Docker 栈 (PostgreSQL + Redis + Meili + Ollama + Reverb + Queue)..."
docker compose up -d --build

echo "⏳ 等待服务就绪..."
sleep 10

if docker compose exec -T postgres pg_isready -U "${DB_USERNAME:-postgres}" >/dev/null 2>&1; then
    echo "✅ PostgreSQL 就绪"
else
    echo "⚠️ PostgreSQL 尚未就绪，请稍后重试 migrate"
fi

echo "📦 Composer / npm ..."
composer install --no-interaction --prefer-dist
npm install

echo "🗄️ 迁移与种子..."
php artisan migrate --seed --force

php artisan storage:link --force 2>/dev/null || true

echo ""
echo "================================================"
echo "  🎉 开发环境就绪"
echo "================================================"
echo ""
echo "  Terminal 1: php artisan serve --host=0.0.0.0 --port=8000"
echo "  Terminal 2: npm run dev"
echo ""
echo "  (Reverb / Queue 已在 Docker 中运行)"
echo ""
echo "  Mailpit:     http://localhost:8025"
echo "  Meilisearch: http://localhost:7700"
echo ""
