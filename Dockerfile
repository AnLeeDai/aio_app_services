# ──────────────────────────────────────────────────────────────
#  Dockerfile  —  PHP-FPM 8.2  +  Nginx  +  Supervisor
# ──────────────────────────────────────────────────────────────
FROM php:8.2-fpm

# ---------- system deps ----------
RUN apt-get update && \
    apt-get install -y --no-install-recommends \
    nginx supervisor git zip unzip curl \
    libpng-dev libjpeg-dev libzip-dev libonig-dev \
    sqlite3 libsqlite3-dev && \
    docker-php-ext-install \
    pdo_mysql pdo_sqlite mbstring bcmath exif gd zip && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# ---------- install Composer ----------
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ---------- app code ----------
WORKDIR /var/www

# 1️⃣  install PHP deps first (no-dev) — keeps layer cache friendly
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev --no-scripts --prefer-dist --optimize-autoloader --no-interaction

# 2️⃣  copy full source & rebuild autoloader
COPY . .
RUN composer dump-autoload --optimize --no-dev && \
    chown -R www-data:www-data /var/www

# ---------- Nginx & Supervisor configs ----------
# Expect these two files to live in your repo under ./docker/
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# ---------- runtime ----------
ENV PORT=8080
EXPOSE ${PORT}

CMD ["/usr/bin/supervisord","-c","/etc/supervisor/conf.d/supervisord.conf"]
