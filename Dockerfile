# Build frontend assets
FROM node:22-alpine AS frontend

WORKDIR /app

COPY package*.json ./
RUN npm install

COPY . .
RUN npm run build

# PHP Laravel runtime
FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    nginx \
    supervisor \
    bash \
    curl \
    git \
    unzip \
    libzip-dev \
    oniguruma-dev \
    postgresql-dev \
    icu-dev \
    sqlite-dev \
    $PHPIZE_DEPS

RUN docker-php-ext-install \
    pdo_pgsql \
    mbstring \
    bcmath \
    exif \
    pcntl \
    intl \
    zip \
    opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-scripts

COPY . .

COPY --from=frontend /app/public/build ./public/build

RUN composer dump-autoload --optimize \
    && mkdir -p storage/framework/cache \
       storage/framework/sessions \
       storage/framework/views \
       storage/logs \
       storage/app/public \
       bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chown www-data:www-data database \
    && chmod -R 775 storage bootstrap/cache \
    && chmod 775 database

COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/start.sh /usr/local/bin/start.sh

RUN chmod +x /usr/local/bin/start.sh

EXPOSE 10000

CMD ["/usr/local/bin/start.sh"]