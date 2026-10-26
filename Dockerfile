# syntax=docker/dockerfile:1

ARG PHP_VERSION=8.5

# ---------------------------------------------------------------------------
# Base: FrankenPHP (Caddy + PHP 8.5) with the extensions Laravel needs
# ---------------------------------------------------------------------------
FROM dunglas/frankenphp:1-php${PHP_VERSION}-alpine AS base

RUN install-php-extensions bcmath intl opcache pcntl pdo_pgsql zip

WORKDIR /app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ---------------------------------------------------------------------------
# Vendor: production PHP dependencies
# ---------------------------------------------------------------------------
FROM base AS vendor

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# ---------------------------------------------------------------------------
# Assets: Vite build (Wayfinder runs `php artisan` during the build)
# ---------------------------------------------------------------------------
FROM vendor AS assets

RUN apk add --no-cache nodejs npm

COPY package.json package-lock.json .npmrc ./
RUN npm ci --no-audit --no-fund

RUN cp .env.example .env \
    && php artisan key:generate --force \
    && npm run build \
    && rm .env

# ---------------------------------------------------------------------------
# Production image
# ---------------------------------------------------------------------------
FROM base AS production

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=pgsql \
    SESSION_ENCRYPT=true \
    SERVER_NAME=:8080 \
    CADDY_SERVER_EXTRA_DIRECTIVES="header -Server"

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && { \
        echo 'opcache.enable=1'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.memory_consumption=256'; \
        echo 'expose_php=Off'; \
    } > "$PHP_INI_DIR/conf.d/zz-laravel.ini"

RUN addgroup -S laravel && adduser -S -G laravel laravel

COPY --from=vendor --chown=laravel:laravel /app /app
COPY --from=assets --chown=laravel:laravel /app/public/build /app/public/build
COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/entrypoint

RUN rm -f public/hot \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R laravel:laravel storage bootstrap/cache /data/caddy /config/caddy

USER laravel

EXPOSE 8080

VOLUME ["/app/storage"]

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD wget -qO- http://127.0.0.1:8080/up > /dev/null || exit 1

ENTRYPOINT ["entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
