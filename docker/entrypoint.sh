#!/bin/sh
set -e

# Only run migrations and cache on the main app container (not queue/scheduler)
# They detect this by whether CMD is php-fpm
if [ "$1" = "php-fpm" ]; then
    # Auto-generate APP_KEY if not set and persist it so queue/scheduler can reuse it
    KEY_FILE=/var/www/html/storage/app/env/app_key
    mkdir -p "$(dirname "$KEY_FILE")"

    if [ -z "$APP_KEY" ]; then
        if [ -f "$KEY_FILE" ]; then
            export APP_KEY=$(cat "$KEY_FILE")
        else
            export APP_KEY=$(php -r "echo 'base64:'.base64_encode(random_bytes(32));")
            echo "$APP_KEY" > "$KEY_FILE"
        fi
        # Inject into Laravel's runtime config
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
