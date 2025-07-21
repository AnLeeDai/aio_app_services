FROM php:8.2-fpm

ENV TZ=UTC \
    COMPOSER_ALLOW_SUPERUSER=1 \
    PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"

RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      git zip unzip curl cron supervisor \
      libxml2-dev libzip-dev libpng-dev libjpeg-dev libonig-dev \
      sqlite3 libsqlite3-dev \
 && docker-php-ext-install pdo pdo_mysql mbstring bcmath gd zip \
 && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress --no-scripts

COPY . .
RUN chown -R www-data:www-data /var/www && chmod -R 755 /var/www

RUN php artisan package:discover --ansi && cp .env.example .env && php artisan key:generate --force

RUN echo '* * * * * www-data /usr/local/bin/php /var/www/artisan schedule:run >> /var/log/cron.log 2>&1' > /etc/cron.d/laravel \
 && chmod 0644 /etc/cron.d/laravel && crontab /etc/cron.d/laravel

RUN mkdir -p /etc/supervisor/conf.d
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 8080
CMD ["/usr/bin/supervisord","-n","-c","/etc/supervisor/conf.d/supervisord.conf"]
