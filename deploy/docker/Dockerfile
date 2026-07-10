FROM php:8.3-fpm-alpine AS base

# ── 系统依赖 ──
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    git \
    unzip \
    linux-headers \
    oniguruma-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    libxml2-dev \
    libsodium-dev \
    postgresql-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    pdo_pgsql \
    mbstring \
    bcmath \
    gd \
    zip \
    xml \
    sodium

# ── Redis 扩展 ──
RUN pecl install redis && docker-php-ext-enable redis

# ── Composer ──
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ── 工作目录 ──
WORKDIR /var/www

# ── 复制代码 ──
COPY . .

# ── 安装依赖 ──
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# ── 权限 ──
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
    && chmod -R 755 /var/www/storage /var/www/bootstrap/cache

# ── 暴露端口 ──
EXPOSE 80

# ── PHP-FPM 前台运行 ──
CMD ["php-fpm"]
