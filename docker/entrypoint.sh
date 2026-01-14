#!/bin/bash
set -e

echo "🚀 Starting SyncFlow API Container..."

# Wait for database to be ready (Debian 11 compatible)
echo "⏳ Waiting for database..."
max_attempts=30
attempt=0
while [ $attempt -lt $max_attempts ]; do
    if php -r "new PDO('mysql:host=${DB_HOST:-syncflow-db};port=${DB_PORT:-3306}', '${DB_USERNAME:-syncflow_user}', '${DB_PASSWORD:-SyncFlow2024#Secure}');" 2>/dev/null; then
        echo "✅ Database is ready!"
        break
    fi
    attempt=$((attempt + 1))
    echo "⏳ Waiting for database... ($attempt/$max_attempts)"
    sleep 2
done

if [ $attempt -eq $max_attempts ]; then
    echo "⚠️ Database connection timeout, continuing anyway..."
fi

# Setup Laravel
echo "🔧 Setting up Laravel..."

# Install Composer dependencies if vendor directory doesn't exist
if [ ! -d "/var/www/html/vendor" ]; then
    echo "📦 Installing Composer dependencies..."
    composer install --optimize-autoloader --no-dev --no-interaction || echo "⚠️ Composer install failed"
fi

# Generate keys if needed
php artisan key:generate --force || true
php artisan jwt:secret --force || true

# Clear caches
php artisan config:clear || true
php artisan cache:clear || true
php artisan route:clear || true
php artisan view:clear || true

# Try to run migrations (with timeout)
echo "🗃️ Running database setup..."
php artisan migrate --force || echo "⚠️ Migration skipped"

# Set permissions
echo "🔐 Setting permissions..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache || true
chmod -R 775 /var/www/html/storage/app/private/reports || true

echo "🎉 SyncFlow API starting..."

# Start Apache
exec apache2-foreground
