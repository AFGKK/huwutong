#!/bin/bash
set -e

echo "================================================"
echo "  HWT License 一键开发环境启动脚本"
echo "================================================"
echo ""

# 检查 Docker
if ! command -v docker &> /dev/null; then
    echo "❌ Docker 未安装，请先安装 Docker Desktop"
    exit 1
fi

# 检查 .env
if [ ! -f .env ]; then
    echo "📝 创建 .env 文件..."
    cp .env.example .env
    php artisan key:generate
fi

# 启动 Docker 服务
echo "🐳 启动 Docker 服务 (MySQL + Redis + Mailpit)..."
docker compose up -d mysql redis mailpit

# 等待 MySQL 就绪
echo "⏳ 等待 MySQL 就绪..."
for i in $(seq 1 30); do
    if docker compose exec -T mysql mysqladmin ping -proot --silent 2>/dev/null; then
        echo "✅ MySQL 就绪"
        break
    fi
    sleep 2
done

# 安装依赖
echo "📦 安装 Composer 依赖..."
composer install --no-interaction --prefer-dist

echo "📦 安装 npm 依赖..."
npm install

# 运行迁移
echo "🗄️ 运行数据库迁移..."
php artisan migrate --seed --force

# 创建 Storage 链接
php artisan storage:link --force || true

# 优化缓存
php artisan config:cache 2>/dev/null || true
php artisan route:cache 2>/dev/null || true

echo ""
echo "================================================"
echo "  🎉 开发环境就绪！"
echo "================================================"
echo ""
echo "启动所有服务:"
echo "  Terminal 1: php artisan serve --host=0.0.0.0 --port=8000"
echo "  Terminal 2: npm run dev"
echo "  Terminal 3: php artisan horizon"
echo "  Terminal 4: php artisan reverb:start"
echo ""
echo "访问地址:"
echo "  后端 API:    http://localhost:8000"
echo "  前端 HMR:    http://localhost:5173"
echo "  Mailpit:     http://localhost:8025"
echo "  MinIO 管理:  http://localhost:9001"
echo ""
