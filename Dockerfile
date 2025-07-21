################################################################################
# 1. Base image & system libs
################################################################################
FROM php:8.2-fpm

ENV TZ=UTC \
    COMPOSER_ALLOW_SUPERUSER=1          # tránh Composer disable plugin khi chạy root

RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      git zip unzip curl cron supervisor \
      libxml2-dev libzip-dev libpng-dev libjpeg-dev libonig-dev \
      sqlite3 libsqlite3-dev \
 && docker-php-ext-install pdo pdo_mysql mbstring bcmath gd zip \
 && rm -rf /var/lib/apt/lists/*

################################################################################
# 2. Thư mục làm việc & Composer
################################################################################
WORKDIR /var/www
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

################################################################################
# 3. Cài vendor (tắt post-scripts để chưa cần artisan)
################################################################################
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress \
        --no-scripts         # ⬅️ KHÔNG chạy package:discover ngay lúc này

################################################################################
# 4. Copy toàn bộ mã nguồn & phân quyền
################################################################################
COPY . .
RUN chown -R www-data:www-data /var/www \
 && chmod -R 755 /var/www

################################################################################
# 5. Chạy lại scripts Composer & generate key
################################################################################
RUN php artisan package:discover --ansi \
 && cp .env.example .env \
 && php artisan key:generate --force

################################################################################
# 6. Cron: artisan schedule:run mỗi phút
################################################################################
RUN echo '* * * * * www-data cd /var/www && php artisan schedule:run >> /var/log/cron.log 2>&1' \
      > /etc/cron.d/laravel \
 && chmod 0644 /etc/cron.d/laravel \
 && crontab /etc/cron.d/laravel

################################################################################
# 7. Supervisor config  (đảm bảo file tồn tại trong repo!)
################################################################################
RUN mkdir -p /etc/supervisor/conf.d
COPY --chown=root:root docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

################################################################################
# 8. Expose & CMD
################################################################################
EXPOSE 9000
CMD ["/usr/bin/supervisord","-n","-c","/etc/supervisor/conf.d/supervisord.conf"]
