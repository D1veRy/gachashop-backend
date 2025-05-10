# Используем официальный образ PHP с FPM
FROM php:8.1-fpm

# Устанавливаем зависимости
RUN apt-get update && apt-get install -y libpq-dev git unzip

# Устанавливаем Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Копируем проект в контейнер
WORKDIR /var/www/html
COPY . .

# Устанавливаем зависимости с помощью Composer
RUN composer install --no-dev --optimize-autoloader

# Настройка прав на каталоги для Yii2
RUN mkdir -p /var/www/html/runtime /var/www/html/web/assets && \
    chown -R www-data:www-data /var/www/html

# Открываем порт, на котором будет работать PHP-FPM
EXPOSE 9000

# Запуск PHP-FPM
CMD ["php-fpm"]
