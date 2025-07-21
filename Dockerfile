############################################
# 1. Base image & system libs
############################################
FROM php:8.2-fpm

# Timezone (tùy vùng)
ENV TZ=UTC

# Cài libs cần thiết + cron + supervisor
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      git zip unzip curl cron supervisor \
      libxml2-dev libzip-dev libpng-dev libjpeg-dev libonig-dev \
      sqlite3 libsqlite3-dev \
 && docker-php-ext-install pdo pdo_mysql mbstring bcmath gd zip \
 && rm -rf /var/lib/apt/lists/*

############################################
# 2. Thư mục làm việc & Composer
############################################
WORKDIR /var/www

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

############################################
# 3. Copy composer.* trước (tối ưu layer cache)
############################################
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress

############################################
# 4. Copy code & cấp quyền
############################################
COPY . .
RUN chown -R www-data:www-data /var/www \
 && chmod -R 755 /var/www

############################################
# 5. ENV & key
############################################
COPY .env.example .env
RUN php artisan key:generate

############################################
# 6. Cron: schedule:run mỗi phút
############################################
RUN echo '* * * * * www-data cd /var/www && php artisan schedule:run >> /var/log/cron.log 2>&1' \
      > /etc/cron.d/laravel \
 && chmod 0644 /etc/cron.d/laravel \
 && crontab /etc/cron.d/laravel

############################################
# 7. Supervisor config
############################################
RUN mkdir -p /etc/supervisor/conf.d
COPY --chown=root:root docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

############################################
# 8. Expose & CMD
############################################
EXPOSE 9000
CMD ["/usr/bin/supervisord","-n","-c","/etc/supervisor/conf.d/supervisord.conf"]
