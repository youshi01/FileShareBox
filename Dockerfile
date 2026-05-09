FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql gd \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

COPY . .

RUN mkdir -p storage/uploads storage/logs \
    && chown -R www-data:www-data storage public \
    && chmod -R 755 storage

EXPOSE 80
