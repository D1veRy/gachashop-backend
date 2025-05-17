# Используем официальный образ PHP с FPM
FROM php:8.1-fpm

# Устанавливаем зависимости для работы с PostgreSQL и другие необходимые пакеты
RUN apt-get update && apt-get install -y libpq-dev git unzip \
    && docker-php-ext-install pdo_pgsql

# Устанавливаем Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Копируем проект в контейнер
WORKDIR /var/www/html
COPY . .

# Устанавливаем зависимости с помощью Composer
RUN composer install --no-dev --optimize-autoloader

# Устанавливаем права на каталоги для Yii2
RUN mkdir -p /var/www/html/runtime /var/www/html/web/assets && \
    chown -R www-data:www-data /var/www/html

# Открываем порт 10000 (Render требует этого порта)
EXPOSE 10000

# Запуск PHP встроенного сервера на порту 10000
CMD ["php", "-S", "0.0.0.0:10000", "-t", "web"]