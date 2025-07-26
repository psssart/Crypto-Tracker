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

# ------------------------------------------------------------------------------
# For local only: run once migrate:fresh + seed
# ------------------------------------------------------------------------------
if [ "$1" = "php-fpm" ] && [ "$APP_ENV" = "local" ]; then

  echo "Waiting for database to listen on ${DB_HOST}:${DB_PORT}…"

  # TCP‑check loop (netcat)
  until nc -z "$DB_HOST" "$DB_PORT"; do
    sleep 2
    echo "  still waiting…"
  done

  MARKER=/var/www/storage/.initialized
  if [ ! -f "$MARKER" ]; then
    echo "››› Running fresh migrations and seeding (local)…"
    php artisan migrate:fresh --seed --force
    touch "$MARKER"
  fi
fi

exec "$@"
