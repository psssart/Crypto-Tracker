#!/bin/sh
# ------------------------------------------------------------------------------
# Entrypoint script for Laravel container
# - Caches config, routes, views on startup for faster performance
# - Forwards commands (e.g. php-fpm) as PID 1
# ------------------------------------------------------------------------------

set -e

if [ "$1" = "php-fpm" ]; then
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
fi

exec "$@"
