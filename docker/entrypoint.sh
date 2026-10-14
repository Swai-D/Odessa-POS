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

# Queued emails/SMS and the daily reminders need a worker and the scheduler. They run beside Apache in this
# container (one small service); set RUN_WORKERS=0 when they run as separate services.
if [ "${RUN_WORKERS:-1}" = "1" ]; then
    # Each runs in a loop so a crash or the hourly worker restart (frees memory) never leaves them stopped.
    su -s /bin/sh www-data -c 'while true; do php artisan queue:work --sleep=3 --tries=3 --max-time=3600; sleep 2; done' &
    su -s /bin/sh www-data -c 'while true; do php artisan schedule:work; sleep 5; done' &
fi

exec apache2-foreground