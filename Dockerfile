FROM php:8.2-fpm

WORKDIR /var/www

RUN apt-get update && apt-get install -y \
    zip unzip curl git libxml2-dev libzip-dev libpng-dev libjpeg-dev libonig-dev \
    sqlite3 libsqlite3-dev && \
    docker-php-ext-install pdo mbstring exif pcntl bcmath gd zip && \
    apt-get clean

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY . /var/www
COPY --chown=www-data:www-data . /var/www

RUN chmod -R 755 /var/www && \
    composer install --no-interaction --optimize-autoloader && \
    cp .env.example .env && \
    php artisan key:generate && \
    php artisan config:clear && \
    php artisan config:cache

EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
