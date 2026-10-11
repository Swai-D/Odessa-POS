#!/bin/sh
set -eu

port="${PORT:-8080}"
sed -i "s/Listen 80/Listen ${port}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${port}>/" /etc/apache2/sites-available/000-default.conf

chown -R www-data:www-data storage bootstrap/cache
php artisan config:cache
exec apache2-foreground