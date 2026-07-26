ARG NODE_BASE_IMAGE
ARG COMPOSER_BASE_IMAGE
ARG RUNTIME_BASE_IMAGE

FROM ${NODE_BASE_IMAGE} AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

FROM ${COMPOSER_BASE_IMAGE} AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

FROM ${RUNTIME_BASE_IMAGE} AS runtime
RUN install-php-extensions bcmath intl mbstring opcache pcntl pdo_mysql simplexml sockets zip redis
WORKDIR /app
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build
RUN rm -f bootstrap/cache/*.php \
    && php artisan package:discover --ansi \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    /data/caddy /config/caddy \
    && chmod 0555 bin/runtime-entrypoint \
    && chown -R www-data:www-data storage bootstrap/cache /data /config
USER 82:82
EXPOSE 8000 8080
HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 CMD php -r '$body = @file_get_contents("http://127.0.0.1:8000/up"); exit($body === false ? 1 : 0);'
CMD ["frankenphp", "php-server", "--root", "public", "--listen", ":8000"]
