FROM php:8.3-apache

# PHP extension for MySQL and Apache modules
RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite ssl headers

# Production settings: errors are logged, not shown to the user
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Self-signed certificate for local HTTPS
RUN openssl req -x509 -nodes -newkey rsa:2048 -days 365 \
    -subj "/CN=localhost" \
    -keyout /etc/ssl/private/midori-kissa.key \
    -out /etc/ssl/certs/midori-kissa.crt

COPY docker/apache/midori-kissa.conf /etc/apache2/sites-available/000-default.conf
COPY backend /var/www/backend
