FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    libpq-dev git unzip libzip-dev libicu-dev libxml2-dev libpng-dev libjpeg-dev libfreetype6-dev libonig-dev \
    && docker-php-ext-configure zip \
    && docker-php-ext-install pdo_pgsql zip intl mbstring xml gd

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

RUN chown -R www-data:www-data /var/www/html

RUN composer install --no-dev --optimize-autoloader --verbose

RUN mkdir -p runtime web/assets && chown -R www-data:www-data runtime web/assets

EXPOSE 10000

CMD ["php", "-S", "0.0.0.0:10000", "-t", "web"]