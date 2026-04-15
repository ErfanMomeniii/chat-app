FROM composer:2 AS deps

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --prefer-dist --no-dev

# ---- Production image ----
FROM php:8.3-fpm AS production

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libsqlite3-dev \
        nginx \
    && docker-php-ext-install pdo_sqlite \
    && apt-get purge -y --auto-remove libsqlite3-dev \
    && rm -rf /var/lib/apt/lists/* \
    && rm /etc/nginx/sites-enabled/default

COPY docker/nginx.conf /etc/nginx/conf.d/app.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /app
COPY --from=deps /app/vendor ./vendor
COPY . .

RUN mkdir -p var \
    && chown -R www-data:www-data /app/var \
    && chown -R www-data:www-data /app/public

EXPOSE 8080

HEALTHCHECK --interval=10s --timeout=3s \
    CMD php -r "echo file_get_contents('http://localhost:8080/health');" || exit 1

ENTRYPOINT ["entrypoint.sh"]
