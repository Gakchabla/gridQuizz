# FrankenPHP: Caddy-based PHP server that handles requests concurrently, unlike
# `php -S` (single-threaded — every "parallel" front-end fetch was actually queued
# and served one at a time, which is what made the app feel slow).
FROM dunglas/frankenphp:php8.4

RUN install-php-extensions \
    pdo_pgsql \
    intl \
    zip \
    opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# vendor/ is bind-mount-excluded (see docker-compose.yml) and served from a native
# Docker volume instead: file access through the Windows bind mount is slow enough
# (thousands of vendor files re-stat'ed by Opcache on every dev request) to make the
# API unusably slow otherwise. Baking it here seeds that volume on first run.
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-interaction --prefer-dist
