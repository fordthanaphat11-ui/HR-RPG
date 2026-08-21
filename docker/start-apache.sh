#!/bin/sh
set -eu

APP_PORT="${PORT:-8080}"
sed -ri "s/^Listen .*/Listen ${APP_PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${APP_PORT}>/" /etc/apache2/sites-available/000-default.conf

# Initialize database if connection available
if [ -f "/var/www/html/scripts/railway-init.php" ]; then
    php /var/www/html/scripts/railway-init.php || echo "Database auto-init warning: skipped or failed, continuing startup"
fi

# mod_php is not thread-safe with event/worker MPM. Keep exactly one MPM active,
# even if the hosting runtime or a cached image enables another module.
rm -f /etc/apache2/mods-enabled/mpm_event.load \
      /etc/apache2/mods-enabled/mpm_event.conf \
      /etc/apache2/mods-enabled/mpm_worker.load \
      /etc/apache2/mods-enabled/mpm_worker.conf
a2enmod mpm_prefork >/dev/null
apache2ctl configtest

exec apache2-foreground

