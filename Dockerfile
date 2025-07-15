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
FROM php:8.3-fpm AS php-build
# Installing dependencies
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      git \
      unzip \
      libpq-dev \
      libonig-dev \
      pkg-config \
      build-essential \
 && docker-php-ext-install \
      bcmath \
      mbstring \
      pdo_pgsql \
 && pecl install redis \
 && docker-php-ext-enable redis \
 && rm -rf /var/lib/apt/lists/*

COPY --from=php:8.3-fpm /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
# Copy the code and ready-made assets
COPY --from=node-build /var/www        /var/www
COPY --from=node-build /var/www/public /var/www/public

# We build vendor, reset rights
RUN composer install --no-dev --optimize-autoloader --prefer-dist --apcu-autoloader \
  && chown -R www-data:www-data storage bootstrap/cache

RUN apt-get purge -y --auto-remove build-essential git pkg-config \
 && rm -rf /var/lib/apt/lists/*

# Create user
RUN groupadd -g 1000 appuser \
 && useradd  -u 1000 -g appuser -s /bin/sh -M appuser \
 && chown -R appuser:appuser /var/www

USER appuser

# -----------------------------
# Stage 3: minimal runtime
# -----------------------------
FROM php:8.3-fpm-alpine AS runtime
ARG IMAGE_TAG

WORKDIR /var/www

RUN cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini

COPY php-config/custom.ini  /usr/local/etc/php/conf.d/custom.ini
COPY php-config/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY php-config/www.conf    /usr/local/etc/php-fpm.d/www.conf

COPY --from=php-build /var/www        /var/www
COPY --from=php-build /var/www/public /var/www/public

RUN chown -R www-data:www-data /var/www
USER www-data

ENTRYPOINT ["sh", "-c", "php artisan config:cache && php artisan route:cache && php artisan view:cache && php-fpm"]
CMD ["php-fpm"]

# -----------------------------
# Stage 4: site на nginx
# -----------------------------
FROM nginx:alpine AS site
ARG IMAGE_TAG
LABEL version="${IMAGE_TAG}"

# Copy config into nginx
COPY ./nginx/default.conf /etc/nginx/conf.d/default.conf

RUN apk del --no-cache bash curl

# Copy only public from php-build
COPY --from=php-runtime /var/www/public /var/www/public

RUN chown -R www-data:www-data /var/www/public
USER www-data
