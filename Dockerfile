FROM php:8.3-fpm

# 1. Установка системных зависимостей (+ добавлены недостающие)
RUN apt-get update && apt-get install -y \
    libpq-dev git unzip libzip-dev libicu-dev libxml2-dev \
    libpng-dev libjpeg-dev libfreetype6-dev libonig-dev \
    libcurl4-openssl-dev libssl-dev libmagickwand-dev \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_pgsql zip intl mbstring xml gd opcache

# 2. Установка Composer (более надежная версия)
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# 3. Копируем только файлы зависимостей
COPY composer.json composer.lock ./

# 4. Установка зависимостей (с очисткой кеша)
RUN composer clear-cache \
    && composer install --no-dev --no-progress --optimize-autoloader --ignore-platform-reqs

# 5. Создаем необходимые директории
RUN mkdir -p runtime/mpdf_temp web/assets \
    && chmod -R 777 runtime web/assets

# 6. Копируем остальные файлы приложения
COPY . .

# 7. Настройка прав
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/web/assets

EXPOSE 10000
CMD ["php", "-S", "0.0.0.0:10000", "-t", "web"]
