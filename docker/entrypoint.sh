#!/bin/sh

set -e

echo "Starting BSERP Backend..."

# Wait for database to be ready
echo "Waiting for database to be ready..."
DB_HOST=${DB_HOST:-localhost}
DB_PORT=${DB_PORT:-5432}
DB_USERNAME=${DB_USERNAME:-postgres}
RETRY_LIMIT=60
RETRY_COUNT=0

until pg_isready -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USERNAME}" 2>/dev/null || [ $RETRY_COUNT -eq $RETRY_LIMIT ]; do
    RETRY_COUNT=$((RETRY_COUNT + 1))
    echo "Database is unavailable (attempt $RETRY_COUNT/$RETRY_LIMIT) - sleeping 1s..."
    sleep 1
done

if [ $RETRY_COUNT -eq $RETRY_LIMIT ]; then
    echo "ERROR: Database connection timed out after ${RETRY_LIMIT}s"
    exit 1
fi

echo "✓ Database is ready!"

# Run migrations
echo "Running database migrations..."
php /app/artisan migrate --force --quiet || {
    echo "ERROR: Migration failed"
    exit 1
}
echo "✓ Migrations completed"

# Cache configuration (production)
if [ "$APP_ENV" = "production" ]; then
    echo "Caching configuration for production..."
    php /app/artisan config:cache
    php /app/artisan route:cache
    php /app/artisan view:cache
    echo "✓ Configuration cached"
else
    # Development: clear caches
    echo "Clearing caches..."
    php /app/artisan cache:clear
    php /app/artisan config:clear
    php /app/artisan view:clear
    php /app/artisan route:clear
    echo "✓ Caches cleared"
fi

# Generate API documentation
echo "Generating API documentation..."
php /app/artisan l5-swagger:generate || echo "⚠ Warning: Failed to generate Swagger docs (non-critical)"

# Set permissions
echo "Setting permissions..."
chmod -R 755 /app/storage /app/bootstrap/cache || true
chown -R nobody:nobody /app/storage /app/bootstrap/cache || true

echo "✓ Application startup completed successfully!"
echo "Starting application services..."

exec "$@"
