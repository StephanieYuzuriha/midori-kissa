# Stage 1: build the Angular app with Node.js
FROM node:22-alpine AS frontend
WORKDIR /app
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci
COPY frontend/ ./
RUN npm run build

# Stage 2: Apache + PHP serving the Angular app and the API
FROM php:8.3-apache

# PHP extensions: MySQL and GD with JPEG and WebP to convert the product photos (RS12).
# fileinfo, used to check the real type of the photos, is already included in the image
RUN apt-get update \
    && apt-get install -y --no-install-recommends libjpeg62-turbo-dev libpng-dev libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql gd \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite ssl headers

# Production settings: errors are logged, not shown to the user
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Self-signed certificate for local HTTPS
RUN openssl req -x509 -nodes -newkey rsa:2048 -days 365 \
    -subj "/CN=localhost" \
    -keyout /etc/ssl/private/midori-kissa.key \
    -out /etc/ssl/certs/midori-kissa.crt

# Folder for the uploaded photos, writable by Apache. docker-compose mounts a volume here
RUN mkdir -p /var/www/uploads/products && chown -R www-data:www-data /var/www/uploads

COPY docker/apache/midori-kissa.conf /etc/apache2/sites-available/000-default.conf
COPY backend /var/www/backend
COPY --from=frontend /app/dist/frontend/browser /var/www/html
