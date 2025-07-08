ARG IMAGE_TAG=latest
# -----------------------------
# Stage 1: build frontend
# -----------------------------
FROM node:22-alpine AS node-build
WORKDIR /var/www
COPY ./crypto-tracker/package*.json ./crypto-tracker/vite.config.js ./
RUN npm ci
COPY ./crypto-tracker/ ./
RUN npm run build


# -----------------------------
# Stage 2: build php + vendor
# -----------------------------
FROM php:8.3-fpm-alpine AS php-build
# Installing dependencies
RUN apk add --no-cache \
      git \
      unzip \
      postgresql-dev \
      oniguruma-dev \
      pkgconfig \
      build-base \
  && docker-php-ext-install \
      bcmath \
      mbstring \
      pdo_pgsql \
  && pecl install redis \
  && docker-php-ext-enable redis

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
# Copy the code and ready-made assets
COPY --from=node-build /var/www        /var/www
COPY --from=node-build /var/www/public /var/www/public

# We build vendor, reset rights
RUN composer install --no-dev --optimize-autoloader --prefer-dist \
  && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]


# -----------------------------
# Stage 3: site на nginx
# -----------------------------
FROM nginx:alpine AS site
LABEL version="${IMAGE_TAG}"
# Copy config into nginx
COPY ./nginx/default.conf /etc/nginx/conf.d/default.conf
# Copy only public from php-build
COPY --from=php-build /var/www/public /var/www/public

# maybe .ENV variables
