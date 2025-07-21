FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    nginx \
    git curl zip unzip \
    libpng-dev libjpeg-dev libonig-dev libxml2-dev libzip-dev \
    sqlite3 libsqlite3-dev \
    && docker-php-ext-install pdo pdo_mysql mbstring zip exif pcntl bcmath gd

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY ./app /var/www

COPY ./docker/nginx/nginx.conf /etc/nginx/sites-available/default

RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www

RUN composer install --no-dev --optimize-autoloader \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

RUN php artisan storage:link || true

COPY ./docker/start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 8080

CMD ["/start.sh"]
