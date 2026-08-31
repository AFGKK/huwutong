FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    git \
    unzip \
    curl \
    bash \
    oniguruma-dev \
    libzip-dev \
    libxml2-dev \
    libsodium-dev \
    postgresql-dev \
    linux-headers \
    && docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    pdo_pgsql \
    mbstring \
    bcmath \
    zip \
    xml \
    sodium \
    pcntl \
    opcache \
    && pecl install redis \
    && docker-php-ext-enable redis opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY deploy/benchmark/php/opcache.ini /usr/local/etc/php/conf.d/99-opcache-benchmark.ini
COPY deploy/benchmark/php/www.conf /usr/local/etc/php-fpm.d/zz-benchmark.conf
COPY deploy/benchmark/entrypoint-app.sh /usr/local/bin/entrypoint-app.sh
RUN chmod +x /usr/local/bin/entrypoint-app.sh

WORKDIR /var/www

EXPOSE 9000

ENTRYPOINT ["entrypoint-app.sh"]
CMD ["php-fpm"]
