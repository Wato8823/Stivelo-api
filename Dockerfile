# syntax=docker/dockerfile:1
# =============================================================================
# API Stive Clothing — image de production pour Render (offre gratuite)
# PHP 8.3 CLI + PostgreSQL + GD (images) — sert Laravel via le serveur intégré.
# =============================================================================
FROM php:8.3-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip ca-certificates \
        libpq-dev libonig-dev libzip-dev libicu-dev \
        libpng-dev libjpeg-dev libfreetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql pgsql gd intl zip bcmath mbstring exif opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress --prefer-dist \
    && mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

ENV PHP_CLI_SERVER_WORKERS=4

COPY docker/start.sh docker/start.sh
RUN chmod +x docker/start.sh

EXPOSE 10000

CMD ["docker/start.sh"]
