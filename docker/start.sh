#!/bin/sh
set -e

echo "Listen ${PORT:-10000}" > /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:10000>/<VirtualHost *:${PORT:-10000}>/" /etc/apache2/sites-available/000-default.conf

php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec apache2-foreground
