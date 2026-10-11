#!/bin/sh
set -eu

rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf
a2enmod mpm_prefork
apache2ctl -M

port="${PORT:-8080}"
sed -i "s/Listen 80/Listen ${port}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${port}>/" /etc/apache2/sites-available/000-default.conf

chown -R www-data:www-data storage bootstrap/cache
php artisan config:cache
exec apache2-foreground