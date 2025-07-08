# -----------------------------
# Stage 1: build frontend
# -----------------------------
FROM node:22-alpine AS node-build

# Working folder - application root
WORKDIR /var/www

# Copy package*.json and Vite config to cache npm install
COPY ./crypto-tracker/package*.json ./crypto-tracker/vite.config.js ./

RUN npm install

# Copy the entire code (resources, vite.config.js, ...)
COPY ./crypto-tracker/ ./

# Run the build - the result will go to public/build + manifest.json
RUN npm run build



# -----------------------------
# Stage 2: build php image
# -----------------------------
FROM php:8.3-fpm AS php-build

# System dependencies + extensions
RUN apt-get update \
  && apt-get install -y \
       git unzip libpq-dev libonig-dev pkg-config \
  && docker-php-ext-install \
       bcmath mbstring pdo_pgsql \
  && pecl install redis && docker-php-ext-enable redis \
  && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy the entire source code + ready-made assets from node-build
COPY --from=node-build /var/www        /var/www
COPY --from=node-build /var/www/public /var/www/public

# Installing PHP dependencies
RUN composer install --no-dev --optimize-autoloader --prefer-dist \
  && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
