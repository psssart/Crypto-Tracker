ARG IMAGE_TAG=latest
# -------------------------------------------------------------------------------------------------
# Stage 1: Build the Vue/React/Svelte frontend assets with Node.js
# -------------------------------------------------------------------------------------------------
FROM node:22-alpine AS node-build

WORKDIR /var/www

# Copy only package manifests first to leverage Docker cache
COPY ./crypto-tracker/package*.json ./crypto-tracker/vite.config.js ./
RUN npm ci

# Copy the rest of the frontend source and compile production assets
COPY ./crypto-tracker/ ./
RUN npm run build


# -------------------------------------------------------------------------------------------------
# Stage 2: Install PHP, extensions, Composer and build vendor dependencies
# -------------------------------------------------------------------------------------------------
FROM php:8.3-fpm AS php-build

# Install OS-level dependencies needed for PHP extensions and Composer
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

# Use the production PHP configuration as a base
COPY --from=php:8.3-fpm /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini

# Install Composer for PHP dependency management
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy compiled frontend assets from node-build stage
COPY --from=node-build /var/www        /var/www
COPY --from=node-build /var/www/public /var/www/public

# Install PHP dependencies (no dev, optimized autoloader) and fix permissions
RUN composer install --no-dev --optimize-autoloader --prefer-dist --apcu-autoloader \
  && chown -R www-data:www-data storage bootstrap/cache

# Remove build tools to minimize final image size
RUN apt-get purge -y --auto-remove build-essential git pkg-config \
 && rm -rf /var/lib/apt/lists/*

# Create a non‑root user for improved security (UID/GID 1000)
RUN groupadd -g 1000 appuser \
 && useradd  -u 1000 -g appuser -s /bin/sh -M appuser \
 && chown -R appuser:appuser /var/www

USER appuser

# -------------------------------------------------------------------------------------------------
# Stage 3: Assemble minimal PHP runtime with only needed extensions
# -------------------------------------------------------------------------------------------------
FROM php:8.3-fpm-alpine AS runtime
ARG IMAGE_TAG

WORKDIR /var/www

# Copy production PHP config
RUN cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini

# Add custom PHP settings and opcode cache config
COPY php-config/custom.ini  /usr/local/etc/php/conf.d/custom.ini
COPY php-config/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY php-config/www.conf    /usr/local/etc/php-fpm.d/www.conf

# Copy application code and public assets
COPY --from=php-build /var/www        /var/www
COPY --from=php-build /var/www/public /var/www/public

# Install only runtime dependencies, enable extensions, then remove build deps
RUN apk add --no-cache --virtual .build-deps \
      $PHPIZE_DEPS \
      postgresql-dev \
      oniguruma-dev \
 && docker-php-ext-install \
      pdo_pgsql \
      mbstring \
      bcmath \
 && pecl install redis \
 && docker-php-ext-enable redis \
 && apk add --no-cache \
      postgresql-libs \
      oniguruma \
 && apk del .build-deps

# Ensure log and cache directories exist with correct permissions
RUN mkdir -p /var/www/storage/logs \
 && chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

USER www-data

# Add entrypoint script for container startup logic
COPY --chmod=0755 docker-entrypoint.sh /usr/local/bin/docker-entrypoint
ENTRYPOINT ["docker-entrypoint"]
CMD ["php-fpm"]

# -------------------------------------------------------------------------------------------------
# Stage 4: Static asset serving with Nginx
# -------------------------------------------------------------------------------------------------
FROM nginx:alpine AS site
ARG IMAGE_TAG

# Label the image with the version/tag for reference
LABEL version="${IMAGE_TAG}"

# Use custom Nginx configuration
COPY ./nginx/default.conf /etc/nginx/conf.d/default.conf

# Remove unnecessary packages to slim down the image
RUN apk del --no-cache bash curl

# Copy only the public (built) assets from the runtime stage
COPY --from=runtime /var/www/public /var/www/public

# Create nginx cache directories and set ownership
RUN mkdir -p \
      /var/cache/nginx/client_temp \
      /var/cache/nginx/proxy_temp \
      /var/cache/nginx/fastcgi_temp \
 && chown -R nginx:nginx /var/cache/nginx \
 && chown -R nginx:nginx /var/www/public

# (Optional) run Nginx as non‑root
#USER nginx
