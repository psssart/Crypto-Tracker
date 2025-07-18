# Dockerfile
FROM php:8.3-fpm-alpine

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
      pdo \
      pdo_pgsql \
      mbstring \
      zip \
      xml \
      pcntl \
 && pecl install redis \
 && docker-php-ext-enable redis

# 3) Copy Composer from the official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 4) Set working directory
WORKDIR /var/www

# 5) Only copy composer files → install
COPY crypto-tracker/composer.json crypto-tracker/composer.lock* ./

# 6) Install PHP dependencies
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# 7) Copy the rest of the app
COPY crypto-tracker/ ./

# 8) Expose FPM port
EXPOSE 9000

# 9) Default to PHP-FPM
CMD ["php-fpm"]
