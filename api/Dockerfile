FROM php:8.4-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libpq-dev \
        libicu-dev \
        libzip-dev \
    && docker-php-ext-install pdo pdo_pgsql intl zip opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# vendor/ is bind-mount-excluded (see docker-compose.yml) and served from a native
# Docker volume instead: file access through the Windows bind mount is slow enough
# (thousands of vendor files re-stat'ed by Opcache on every dev request) to make the
# API unusably slow otherwise. Baking it here seeds that volume on first run.
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-interaction --prefer-dist
