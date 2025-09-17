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
    nginx \
    gettext-base \
    ; \
    docker-php-ext-configure zip; \
    docker-php-ext-install -j"$(nproc)" mbstring zip; \
    rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV PORT=8080
ENV ENABLE_NGINX=1
# Allow Composer to run as root (inside container)
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html

# Leverage build cache: install PHP dependencies first
COPY composer.json composer.lock ./
# If composer.lock is out of sync with composer.json, fall back to update
RUN set -eux; \
    composer install --no-dev --no-scripts --optimize-autoloader --no-interaction --prefer-dist \
    || composer update --no-dev --no-scripts --no-interaction --prefer-dist

# Then copy the rest of the application code
COPY . .

# Run composer again to execute scripts now that app code is present
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Ensure Laravel runtime directories exist and are writable (for PaaS single-container builds)
RUN set -eux; \
    mkdir -p storage/framework/{cache,sessions,testing,views} storage/logs bootstrap/cache; \
    chown -R www-data:www-data storage bootstrap/cache; \
    chmod -R ug+rwX storage bootstrap/cache

# Copy nginx template and entrypoint
COPY conf/nginx/nginx-site.template /etc/nginx/templates/default.conf.template
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Expose FPM and HTTP ports
EXPOSE 9000 8080

# Start via entrypoint (runs nginx + php-fpm when ENABLE_NGINX=1)
CMD ["/usr/local/bin/entrypoint.sh"]
