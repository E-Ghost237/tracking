# syntax=docker/dockerfile:1.7
# One image for web (php-fpm), queue worker and scheduler (section 10.1).

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

FROM node:24-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY resources ./resources
COPY vite.config.js ./
COPY public ./public
# The Filament admin theme imports Filament's CSS sources from vendor.
COPY --from=vendor /app/vendor/filament ./vendor/filament
RUN npm run build

FROM php:8.4-fpm-alpine AS app
RUN apk add --no-cache icu-libs libpq libzip imagemagick libpng libjpeg-turbo libwebp freetype fcgi \
 && apk add --no-cache --virtual .build $PHPIZE_DEPS icu-dev postgresql-dev libzip-dev imagemagick-dev libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev linux-headers \
 && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
 && docker-php-ext-install -j"$(nproc)" intl pdo_pgsql pcntl bcmath zip gd opcache exif \
 && pecl install redis imagick && docker-php-ext-enable redis imagick \
 && apk del .build

COPY docker/php/production.ini /usr/local/etc/php/conf.d/zz-production.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-www.conf

WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

RUN php artisan package:discover --ansi \
 && php artisan filament:assets \
 && rm -rf tests .git node_modules storage/logs/*.log \
 && chown -R www-data:www-data storage bootstrap/cache

USER www-data
HEALTHCHECK --interval=30s --timeout=5s CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 || exit 1
CMD ["php-fpm"]
