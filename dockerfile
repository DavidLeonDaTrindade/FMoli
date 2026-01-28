FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libicu-dev libonig-dev \
    && docker-php-ext-install pdo_mysql zip intl mbstring

RUN a2enmod rewrite
WORKDIR /var/www/html

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . /var/www/html
RUN if [ -f composer.json ]; then composer install --no-interaction; fi

RUN chown -R www-data:www-data /var/www/html

