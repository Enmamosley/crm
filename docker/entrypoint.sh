#!/bin/sh

# Load persisted APP_KEY for ALL containers (app, queue, scheduler)
KEY_FILE=/var/www/html/storage/app/env/app_key
if [ -z "$APP_KEY" ] && [ -f "$KEY_FILE" ]; then
    export APP_KEY=$(cat "$KEY_FILE")
    sed -i "s|APP_KEY=.*|APP_KEY=$APP_KEY|" /var/www/html/.env 2>/dev/null || \
        echo "APP_KEY=$APP_KEY" >> /var/www/html/.env
fi

# Only run migrations, key generation, and cache on the main app container
if [ "$1" = "php-fpm" ]; then
    # Ensure storage directories exist (volumes may be empty on first boot)
    mkdir -p /var/www/html/storage/framework/{cache,sessions,views}
    mkdir -p /var/www/html/storage/logs
    mkdir -p "$(dirname "$KEY_FILE")"
    chown -R www-data:www-data /var/www/html/storage

    if [ -z "$APP_KEY" ]; then
        export APP_KEY=$(php -r "echo 'base64:'.base64_encode(random_bytes(32));")
        echo "$APP_KEY" > "$KEY_FILE"
        sed -i "s|APP_KEY=.*|APP_KEY=$APP_KEY|" /var/www/html/.env 2>/dev/null || \
            echo "APP_KEY=$APP_KEY" >> /var/www/html/.env
    fi

    echo "→ Running migrations..."
    if ! php artisan migrate --force; then
        echo "⚠ Migrations failed — container will start anyway (check logs)"
    fi

    echo "→ Caching config / routes / views..."
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true

    echo "→ Linking storage..."
    php artisan storage:link --force 2>/dev/null || true
fi

echo "→ Starting: $*"
exec "$@"
