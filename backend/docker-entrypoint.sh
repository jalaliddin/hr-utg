#!/bin/sh
set -e

# Wait for MySQL
echo "MySQL kutilmoqda..."
until php artisan tinker --execute="DB::connection()->getPdo();" 2>/dev/null; do
    sleep 2
done
echo "MySQL tayyor."

# Run migrations
php artisan migrate --force --no-interaction

# Storage link
php artisan storage:link --force 2>/dev/null || true

# Clear & cache for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "Laravel tayyor. Ishga tushmoqda..."
exec "$@"
