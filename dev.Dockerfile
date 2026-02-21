# Dockerfile
FROM php:8.3-fpm-alpine

# 0) Copy the local .env into the container
COPY .env /var/www/.env

# 1) Install system dependencies
RUN apk update \
 && apk add --no-cache \
    bash \
    build-base \
    autoconf \
    zlib-dev \
    libzip-dev \
    oniguruma-dev \
    libxml2-dev \
    git \
    curl \
    nodejs \
    npm \
    postgresql-dev \
 && rm -rf /var/cache/apk/*

# 2) Install & enable PHP extensions
RUN docker-php-ext-install \
      bcmath \
      pdo \
      pdo_pgsql \
      mbstring \
      zip \
      xml \
      pcntl \
      opcache \
 && pecl install redis \
 && docker-php-ext-enable redis

# 3) Dev PHP settings: disable opcache so code changes are instant
RUN { \
  echo "opcache.enable=0"; \
  echo "opcache.enable_cli=0"; \
  echo "opcache.validate_timestamps=1"; \
  echo "opcache.revalidate_freq=0"; \
} > /usr/local/etc/php/conf.d/99-dev.ini

# 4) Copy Composer from the official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 5) Set working directory
WORKDIR /var/www

# 6) Only copy composer files → install
COPY crypto-tracker/composer.json crypto-tracker/composer.lock* ./

# 7) Install PHP dependencies
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# 8) Copy the rest of the app
COPY crypto-tracker/ ./

# 9) Expose FPM port
EXPOSE 9000

# 10) Use docker entrypoint
 # Copy our custom entrypoint script into the container and make it executable
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

 # Use the entrypoint script to bootstrap the container (migrations, seeding, etc.)
ENTRYPOINT ["docker-entrypoint.sh"]

# 11) Default to PHP-FPM
CMD ["php-fpm"]
