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

  echo "››› Running pending migrations…"
  php artisan migrate --force

  if [ "$APP_ENV" = "local" ]; then
    echo "››› Running seeders (idempotent)…"
    php artisan db:seed --force

    echo "››› Fetching current Ngrok URL..."
    NGROK_PUBLIC_URL=$(curl -s http://tunnel:4040/api/tunnels | grep -o 'https://[^"]*ngrok-free.dev' | head -n 1)

    if [ -n "$NGROK_PUBLIC_URL" ]; then
        echo "››› Registering Webhook with: $NGROK_PUBLIC_URL"
        php artisan nutgram:hook:set "$NGROK_PUBLIC_URL/api/webhooks/telegram/webhook"
    else
        echo "››› [Warning] Could not fetch Ngrok URL. Is the tunnel container running?"
    fi
  else
    echo "››› Registering Telegram Bot Commands & Webhook..."
    php artisan nutgram:register-commands
    php artisan nutgram:hook:set "${APP_URL}/api/telegram/webhook"
  fi
fi

exec "$@"
