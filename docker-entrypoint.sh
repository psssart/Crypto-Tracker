#!/bin/sh
# ------------------------------------------------------------------------------
# Entrypoint script for Laravel container
# - Caches config, routes, views on startup for faster performance
# - Forwards commands (e.g. php-fpm) as PID 1
# ------------------------------------------------------------------------------

set -e

if [ "$1" = "php-fpm" ]; then
  if [ "$APP_ENV" = "local" ]; then
    echo "››› Local environment – clearing Laravel caches"
    php artisan optimize:clear
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
  else
    echo "››› Production environment – optimizing Laravel caches"
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
  fi

  echo "Waiting for database to listen on ${DB_HOST}:${DB_PORT}…"
  until nc -z "$DB_HOST" "$DB_PORT"; do
    sleep 2
    echo "  still waiting…"
  done

  # Local-only fresh migrate + seed (your existing behavior)
  if [ "$APP_ENV" = "local" ]; then
    MARKER=/var/www/storage/.initialized
    if [ ! -f "$MARKER" ]; then
      echo "››› Running migrate:fresh --seed (local only)…"
      php artisan migrate:fresh --seed --force
      touch "$MARKER"
    fi
  fi
fi

exec "$@"
