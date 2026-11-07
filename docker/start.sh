#!/bin/sh
set -e

cd /var/www/html

mkdir -p storage/framework/cache \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs \
         storage/app/public

chown -R www-data:www-data storage bootstrap/cache

php artisan storage:link || true

php artisan config:cache

exec /usr/bin/supervisord -c /etc/supervisord.conf