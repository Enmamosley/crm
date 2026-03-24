# ─────────────────────────────────────────────────────────
# Stage 1: Build frontend assets (Node)
# ─────────────────────────────────────────────────────────
FROM node:20-alpine AS assets

WORKDIR /app

COPY package*.json ./
RUN npm ci --ignore-scripts

COPY vite.config.js ./
COPY resources/ resources/
COPY public/ public/

RUN npm run build

# ─────────────────────────────────────────────────────────
# Stage 2: PHP-FPM application
# ─────────────────────────────────────────────────────────
FROM php:8.2-fpm-alpine AS app

# System dependencies
RUN apk add --no-cache \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    mysql-client \
    git \
    unzip \
    curl

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        bcmath \
        gd \
        zip \
        intl \
        opcache \
        pcntl

# PHP production settings
RUN { \
        echo "opcache.enable=1"; \
        echo "opcache.memory_consumption=128"; \
        echo "opcache.interned_strings_buffer=8"; \
        echo "opcache.max_accelerated_files=10000"; \
        echo "opcache.revalidate_freq=0"; \
        echo "opcache.validate_timestamps=0"; \
    } > /usr/local/etc/php/conf.d/opcache.ini \
 && { \
        echo "upload_max_filesize=20M"; \
        echo "post_max_size=20M"; \
        echo "memory_limit=256M"; \
        echo "max_execution_time=120"; \
    } > /usr/local/etc/php/conf.d/app.ini

# Composer
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Application files
COPY --chown=www-data:www-data . .

# Built assets from node stage
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

# PHP dependencies (production only)
RUN composer install \
        --no-dev \
        --optimize-autoloader \
        --no-interaction \
        --no-progress \
 && chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]

# ─────────────────────────────────────────────────────────
# Stage 3: Nginx web server
# ─────────────────────────────────────────────────────────
FROM nginx:1.27-alpine AS web

# Public files (static assets baked in from PHP stage)
COPY --from=app /var/www/html/public /var/www/html/public

# Nginx configuration
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
