#!/bin/sh
set -e

if [ "$1" = "php-fpm" ]; then
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
fi

exec "$@"
