# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# base: PHP-FPM with the extensions the application needs
# ---------------------------------------------------------------------------
FROM php:8.4-fpm-alpine AS base

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions pdo_mysql opcache redis zip \
    && apk add --no-cache fcgi

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf

WORKDIR /var/www/html

# ---------------------------------------------------------------------------
# build: install production dependencies
# ---------------------------------------------------------------------------
FROM base AS build

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative --no-interaction

# ---------------------------------------------------------------------------
# test: dev dependencies plus a coverage driver, for running the test suite
# ---------------------------------------------------------------------------
FROM build AS test

RUN install-php-extensions pcov \
    && composer install --prefer-dist --no-interaction --no-progress \
    && echo "opcache.enable_cli=0" > /usr/local/etc/php/conf.d/zz-test.ini

CMD ["php", "artisan", "test", "--coverage", "--min=95"]

# ---------------------------------------------------------------------------
# web: nginx serving static files and proxying PHP to the app container
# ---------------------------------------------------------------------------
FROM nginx:1.27-alpine AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=build /var/www/html/public /var/www/html/public

# ---------------------------------------------------------------------------
# app: the production PHP-FPM image (default target)
# ---------------------------------------------------------------------------
FROM base AS app

COPY --from=build --chown=www-data:www-data /var/www/html /var/www/html
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint

RUN chmod +x /usr/local/bin/entrypoint

USER www-data

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
