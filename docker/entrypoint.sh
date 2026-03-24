#!/bin/sh
set -e

# Load persisted APP_KEY for ALL containers (app, queue, scheduler)
KEY_FILE=/var/www/html/storage/app/env/app_key
if [ -z "$APP_KEY" ] && [ -f "$KEY_FILE" ]; then
    export APP_KEY=$(cat "$KEY_FILE")
    sed -i "s|APP_KEY=.*|APP_KEY=$APP_KEY|" /var/www/html/.env 2>/dev/null || \
        echo "APP_KEY=$APP_KEY" >> /var/www/html/.env
fi

# Only run migrations, key generation, and cache on the main app container
if [ "$1" = "php-fpm" ]; then
    mkdir -p "$(dirname "$KEY_FILE")"

    if [ -z "$APP_KEY" ]; then
        export APP_KEY=$(php -r "echo 'base64:'.base64_encode(random_bytes(32));")
        echo "$APP_KEY" > "$KEY_FILE"
        sed -i "s|APP_KEY=.*|APP_KEY=$APP_KEY|" /var/www/html/.env 2>/dev/null || \
            echo "APP_KEY=$APP_KEY" >> /var/www/html/.env
    fi

    echo "→ Running migrations..."
    php artisan migrate --force

    echo "→ Caching config / routes / views..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache

    echo "→ Linking storage..."
    php artisan storage:link --force 2>/dev/null || true
fi

echo "→ Starting: $*"
exec "$@"
