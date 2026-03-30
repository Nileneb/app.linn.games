#!/bin/bash
set -e

# Wait for database to be ready
echo "Waiting for database..."
while ! php artisan tinker --execute="DB::connection()->getPdo();" > /dev/null 2>&1; do
    sleep 2
done
echo "Database is ready!"

# Run migrations in production/staging
if [ "$APP_ENV" = "production" ] || [ "$APP_ENV" = "staging" ]; then
    echo "Running database migrations..."
    php artisan migrate --force
fi

# Clear and cache config/routes/views for production
if [ "$APP_ENV" = "production" ]; then
    echo "Caching configuration..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

# Execute the main command (php-fpm or queue:work)
exec "$@"
