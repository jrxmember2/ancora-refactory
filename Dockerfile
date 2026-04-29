# syntax=docker/dockerfile:1.7

FROM composer:2 AS composer

FROM php:8.3-apache AS vendor
WORKDIR /app
COPY --from=composer /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip zip libzip-dev libicu-dev libonig-dev libxml2-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install intl zip mbstring gd \
    && rm -rf /var/lib/apt/lists/*

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader --no-scripts \
    && composer update mpdf/mpdf --with-all-dependencies --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader --no-scripts

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

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip zip libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libicu-dev libonig-dev libxml2-dev python3 python3-pip python3-venv chromium poppler-utils fonts-dejavu-core fonts-liberation \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql intl zip mbstring gd \
    && python3 -m venv /opt/pyenv \
    && /opt/pyenv/bin/pip install --no-cache-dir openpyxl==3.1.5 xlrd==2.0.1 websocket-client==1.8.0 \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

ENV PATH="/opt/pyenv/bin:${PATH}"
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf

RUN { \
        echo 'upload_max_filesize=64M'; \
        echo 'post_max_size=72M'; \
        echo 'max_file_uploads=30'; \
        echo 'memory_limit=256M'; \
    } > /usr/local/etc/php/conf.d/ancora-uploads.ini

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

RUN touch .env \
    && mkdir -p \
        storage/app/public/avatars/users \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
        public/uploads \
        public/assets/uploads \
        public/branding \
        public/build \
    && ln -sfn /var/www/html/storage/app/public /var/www/html/public/storage \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache public/uploads public/assets/uploads public/branding public/build

EXPOSE 80
CMD ["apache2-foreground"]
