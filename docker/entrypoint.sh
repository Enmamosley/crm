#!/bin/sh
set -e

# Only run migrations and cache on the main app container (not queue/scheduler)
# They detect this by whether CMD is php-fpm
if [ "$1" = "php-fpm" ]; then
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
