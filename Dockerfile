FROM php:8.3-fpm

# 1. Установка системных зависимостей
RUN apt-get update && apt-get install -y \
    libpq-dev git unzip libzip-dev libicu-dev libxml2-dev \
    libpng-dev libjpeg-dev libfreetype6-dev libonig-dev \
    libcurl4-openssl-dev libssl-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_pgsql zip intl mbstring xml gd opcache

# 2. Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 3. Копируем только файлы зависимостей
COPY composer.json composer.lock ./

# 4. Установка зависимостей (включая mPDF)
RUN composer install --no-dev --no-progress --optimize-autoloader --verbose || \
    (cat composer.lock && composer show -i && exit 1)

# 5. Создаем необходимые директории
RUN mkdir -p runtime/mpdf_temp web/assets && \
    chown -R www-data:www-data runtime web/assets

# 6. Копируем остальные файлы приложения
COPY . .

# 7. Настройка прав
RUN chown -R www-data:www-data /var/www/html

EXPOSE 10000

CMD ["php", "-S", "0.0.0.0:10000", "-t", "web"]
