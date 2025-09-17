#!/usr/bin/env sh
set -eu

# Default values
: "${PORT:=8080}"
: "${ENABLE_NGINX:=1}"

# Ensure runtime dirs exist
mkdir -p /run/php /var/run /var/cache/nginx /var/log/nginx \
  /var/www/html/storage/framework/cache \
  /var/www/html/storage/framework/sessions \
  /var/www/html/storage/framework/testing \
  /var/www/html/storage/framework/views \
  /var/www/html/storage/logs \
  /var/www/html/bootstrap/cache

# Fix permissions for Laravel writable paths (ignore errors in read-only fs)
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
chmod -R ug+rwX /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

# Render nginx config if enabled
if [ "$ENABLE_NGINX" = "1" ]; then
  mkdir -p /etc/nginx/templates || true
  if [ -f /etc/nginx/templates/default.conf.template ]; then
    envsubst '$PORT' < /etc/nginx/templates/default.conf.template > /etc/nginx/conf.d/default.conf
  fi
fi

# Start php-fpm in background when using nginx, else foreground
if [ "$ENABLE_NGINX" = "1" ]; then
  php-fpm -D
  nginx -g "daemon off;"
else
  exec php-fpm
fi
