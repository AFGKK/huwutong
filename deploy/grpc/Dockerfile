# ─── gRPC PHP 服务 Dockerfile ───
# M1.3-28 gRPC 服务间通信
# 多阶段构建：支持 license/device/billing/notification 四个服务

ARG SERVICE=license

# ─── 基础阶段 ───
FROM php:8.3-cli AS base

RUN apt-get update && apt-get install -y \
    git unzip libpq-dev libssl-dev \
    && pecl install grpc protobuf \
    && docker-php-ext-enable grpc protobuf \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# ─── License 服务 ───
FROM base AS license-service
COPY . .
RUN composer install --no-dev --optimize-autoloader
EXPOSE 50052
CMD ["php", "artisan", "grpc:serve", "--port=50052", "--workers=4"]

# ─── Device 服务 ───
FROM base AS device-service
COPY . .
RUN composer install --no-dev --optimize-autoloader
EXPOSE 50053
CMD ["php", "artisan", "grpc:serve", "--port=50053", "--workers=4"]

# ─── Billing 服务 ───
FROM base AS billing-service
COPY . .
RUN composer install --no-dev --optimize-autoloader
EXPOSE 50054
CMD ["php", "artisan", "grpc:serve", "--port=50054", "--workers=4"]

# ─── Notification 服务 ───
FROM base AS notification-service
COPY . .
RUN composer install --no-dev --optimize-autoloader
EXPOSE 50055
CMD ["php", "artisan", "grpc:serve", "--port=50055", "--workers=4"]

# ─── 默认目标 ───
FROM ${SERVICE}-service
