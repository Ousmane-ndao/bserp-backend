#!/bin/sh

set -e

echo "Starting BSERP Backend..."

mkdir -p /var/run /run /var/log/nginx /var/log/supervisor

# Wait for database to be ready (Neon compute can take ~30s to wake).
echo "Waiting for database to be ready..."
DB_HOST=${DB_HOST:-localhost}
DB_PORT=${DB_PORT:-5432}
DB_USERNAME=${DB_USERNAME:-postgres}
RETRY_LIMIT=90
RETRY_COUNT=0

echo "Resolving DB_HOST=${DB_HOST}..."
if ! getent hosts "${DB_HOST}" >/dev/null 2>&1; then
    echo "WARNING: getent could not resolve ${DB_HOST} (will retry via pg_isready)"
    getent hosts "${DB_HOST}" || true
fi

until pg_isready -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USERNAME}" 2>/dev/null || [ $RETRY_COUNT -eq $RETRY_LIMIT ]; do
    RETRY_COUNT=$((RETRY_COUNT + 1))
    echo "Database is unavailable (attempt $RETRY_COUNT/$RETRY_LIMIT) - sleeping 1s..."
    sleep 1
done

if [ $RETRY_COUNT -eq $RETRY_LIMIT ]; then
    echo "ERROR: Database connection timed out after ${RETRY_LIMIT}s"
    echo "DNS lookup for ${DB_HOST}:"
    getent hosts "${DB_HOST}" || true
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

# Seeders (dont SecureUserSeeder, qui réécrit les mots de passe des comptes listés)
# ne doivent PAS tourner à chaque redémarrage sur une base contenant déjà de vraies
# données de prod : opt-in explicite via RUN_SEEDERS=true (ex. premier déploiement
# sur une base vide), sinon on ne fait rien ici.
if [ "${RUN_SEEDERS:-false}" = "true" ]; then
    echo "Synchronizing database seeders (RUN_SEEDERS=true)..."
    php /app/artisan db:seed --force --quiet || {
        echo "ERROR: Database seed failed"
        exit 1
    }
    echo "✓ Database seeders completed"
else
    echo "Skipping seeders (RUN_SEEDERS not set to true)"
fi

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
php /app/artisan app:generate-swagger || echo "⚠ Warning: Failed to generate Swagger docs (non-critical)"

# Ensure document upload directory exists (persistent disk mount: /app/storage)
echo "Ensuring storage directories..."
mkdir -p /app/storage/app/private/documents /app/storage/app/public || true

# Set permissions
echo "Setting permissions..."
chmod -R 755 /app/storage /app/bootstrap/cache || true
chown -R www-data:www-data /app/storage /app/bootstrap/cache || true

echo "✓ Application startup completed successfully!"
echo "Starting application services..."

exec "$@"
