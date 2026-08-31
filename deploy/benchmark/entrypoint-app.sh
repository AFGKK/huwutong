#!/bin/sh
set -e

cd /var/www

export BENCHMARK_RUNTIME=nginx-php-fpm

if [ ! -f vendor/autoload.php ]; then
    echo "[benchmark-app] Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

if [ -f .env ]; then
    php artisan config:cache --no-ansi 2>/dev/null || true
    php artisan route:cache --no-ansi 2>/dev/null || true
fi

echo "[benchmark-app] PHP-FPM ready (OPcache + Redis extensions)"
exec docker-php-entrypoint "$@"
