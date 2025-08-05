FROM composer:2.7 as composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --prefer-dist --no-interaction
COPY . .

FROM php:8.2-fpm-alpine
WORKDIR /var/www

RUN apk add --no-cache libpng libjpeg-turbo libzip-dev oniguruma-dev \
    && docker-php-ext-install pdo_mysql mbstring bcmath zip exif gd

COPY --from=composer /app /var/www

RUN addgroup -g 1000 www && adduser -u 1000 -G www -s /bin/sh -D www \
    && chown -R www:www /var/www
USER www

ENV APP_ENV=production \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0

EXPOSE 9000
CMD ["php-fpm"]
