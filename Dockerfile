# syntax=docker/dockerfile:1.7

FROM composer:2 AS composer

FROM php:8.3-cli AS vendor
WORKDIR /app
COPY --from=composer /usr/bin/composer /usr/bin/composer

RUN apt-get update && apt-get install -y \
    git unzip zip libzip-dev libicu-dev libonig-dev libxml2-dev \
    && docker-php-ext-install intl zip \
    && rm -rf /var/lib/apt/lists/*

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader --no-scripts

FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY public ./public
COPY vite.config.js ./
RUN npm run build

FROM php:8.3-apache AS runtime
WORKDIR /var/www/html

RUN apt-get update && apt-get install -y \
    git unzip zip libzip-dev libpng-dev libicu-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo pdo_mysql intl zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

RUN touch .env \
    && mkdir -p \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
        public/uploads \
        public/assets/uploads \
        public/branding \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache public/uploads public/assets/uploads public/branding

EXPOSE 80
CMD ["apache2-foreground"]