#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

# Install PHP dependencies
if [ ! -d "vendor" ]; then
  composer install --no-interaction --prefer-dist --no-progress
fi

# Ensure .env exists and app key is set
if [ ! -f ".env" ]; then
  cp .env.example .env
fi

php artisan key:generate --force --no-interaction || true

# Run migrations (ignore failures in dev if DB is locked/not ready)
php artisan migrate --force || true

echo "Starting Laravel development server on 0.0.0.0:8000"
exec php artisan serve --host=0.0.0.0 --port=8000


