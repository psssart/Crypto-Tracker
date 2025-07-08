# Stage 1 — Node for Vite
FROM node:22-alpine as node-build
WORKDIR /app
COPY ./crypto-tracker/package*.json ./
RUN npm install
COPY ./crypto-tracker/ ./
RUN npm run build

# Stage 2 — PHP (Composer, extensions, app)
FROM php:8.3-fpm as php-build

RUN apt-get update && apt-get install -y \
  git unzip libpq-dev libonig-dev pkg-config \
  && docker-php-ext-install bcmath mbstring pdo_pgsql \
  && pecl install redis && docker-php-ext-enable redis \
  && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY ./crypto-tracker/ ./
COPY --from=node-build /app/public /var/www/public

RUN composer install --no-dev --optimize-autoloader --prefer-dist \
  && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
