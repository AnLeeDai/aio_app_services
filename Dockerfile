FROM php:8.2-fpm

# Install system dependencies and build PHP extensions
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        git \
        curl \
        unzip \
        zip \
        libzip-dev \
        libonig-dev \
        zlib1g-dev \
    ; \
    docker-php-ext-configure zip; \
    docker-php-ext-install -j"$(nproc)" mbstring zip; \
    rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Leverage build cache: install PHP dependencies first
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Then copy the rest of the application code
COPY . .

CMD ["php-fpm"]
