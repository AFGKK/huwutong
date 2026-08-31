#!/bin/sh
set -e

cd /var/www

export BENCHMARK_RUNTIME=swoole

if [ ! -f vendor/autoload.php ]; then
    echo "[benchmark-octane] Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

if [ -f .env ]; then
    php artisan config:cache --no-ansi 2>/dev/null || true
    php artisan route:cache --no-ansi 2>/dev/null || true
    php artisan event:cache --no-ansi 2>/dev/null || true
fi

echo "[benchmark-octane] Laravel Octane (Swoole) ready — workers=16, max_requests=5000"
exec "$@"
