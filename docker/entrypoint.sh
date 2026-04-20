#!/bin/sh

set -e

echo "Starting BSERP Backend..."

# Wait for database to be ready
echo "Waiting for database..."
until pg_isready -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USERNAME}" 2>/dev/null; do
    echo "Postgres is unavailable - sleeping"
    sleep 1
done

echo "Postgres is up!"

# Run migrations
echo "Running database migrations..."
php /app/artisan migrate --force

# Clear caches
echo "Clearing caches..."
php /app/artisan cache:clear
php /app/artisan config:clear
php /app/artisan view:clear
php /app/artisan route:clear

# Generate API documentation
echo "Generating API documentation..."
php /app/artisan l5-swagger:generate || true

# Set permissions
echo "Setting permissions..."
chmod -R 755 /app/storage /app/bootstrap/cache

echo "Application is ready!"

exec "$@"
