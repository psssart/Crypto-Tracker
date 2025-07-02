FROM php:8.3-fpm

RUN apt-get update \
  && apt-get install -y \
       git unzip libpq-dev libonig-dev pkg-config \
  && docker-php-ext-install \
       bcmath \
       mbstring \
       pdo_pgsql \
  && rm -rf /var/lib/apt/lists/*

RUN pecl install redis && docker-php-ext-enable redis

# Composer binary
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Firstly copy whole code
COPY ./crypto-tracker/ /var/www/

# Install packets
RUN composer install --no-dev --optimize-autoloader --prefer-dist \
  && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
