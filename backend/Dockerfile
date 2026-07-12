FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

FROM dunglas/frankenphp:php8.4-alpine AS runtime
RUN install-php-extensions intl pcntl pdo_mysql sockets
RUN install-php-extensions redis
WORKDIR /app
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build
RUN rm -f bootstrap/cache/*.php \
    && php artisan package:discover --ansi \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    /data/caddy /config/caddy \
    && chown -R www-data:www-data storage bootstrap/cache /data /config
USER www-data
EXPOSE 8000 8080
HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 CMD php -r '$body = @file_get_contents("http://127.0.0.1:8000/health"); exit($body === false ? 1 : 0);'
CMD ["frankenphp", "php-server", "--root", "public", "--listen", ":8000"]
