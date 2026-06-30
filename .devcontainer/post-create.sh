#!/bin/bash
# HWT License DevContainer 初始化脚本
set -e

echo "🚀 HWT License DevContainer 初始化中..."

# ── 复制环境配置 ──
if [ ! -f .env ]; then
    cp .env.example .env
    echo "✅ .env 已创建"
fi

# ── 安装 PHP 依赖 ──
if [ ! -d vendor ]; then
    composer install --no-interaction --prefer-dist
    echo "✅ Composer 依赖已安装"
fi

# ── 安装前端依赖 ──
if [ ! -d node_modules ]; then
    npm ci
    echo "✅ npm 依赖已安装"
fi

# ── 生成 APP_KEY ──
php artisan key:generate --force
echo "✅ APP_KEY 已生成"

# ── 等待 MySQL 就绪 ──
echo "⏳ 等待 MySQL..."
for i in $(seq 1 30); do
    if php -r "new PDO('mysql:host=localhost;port=3306','root','root');" 2>/dev/null; then
        echo "✅ MySQL 就绪"
        break
    fi
    sleep 2
done

# ── 运行迁移 ──
php artisan migrate --seed --force
echo "✅ 数据库迁移完成"

# ── 创建 Storage 链接 ──
php artisan storage:link --force || true

# ── 缓存优化 ──
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo ""
echo "🎉 HWT License DevContainer 初始化完成！"
echo ""
echo "📌 启动方式:"
echo "   后端: php artisan serve --host=0.0.0.0 --port=8000"
echo "   前端: npm run dev"
echo "   队列: php artisan horizon"
echo "   WS:   php artisan reverb:start"
echo ""
echo "📌 访问地址:"
echo "   管理后台: http://localhost:8000/admin"
echo "   客户门户: http://localhost:8000/portal"
echo "   Mailpit:  http://localhost:8025"
echo "   Vite:     http://localhost:5173"
