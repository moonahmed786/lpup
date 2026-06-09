#!/bin/sh
set -e

# Ensure runtime-writable directories exist.
mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

find storage bootstrap/cache -type d -exec chmod 775 {} \; 2>/dev/null || true
find storage bootstrap/cache -type f ! -name 'oauth-*.key' -exec chmod 664 {} \; 2>/dev/null || true
chmod 600 storage/oauth-*.key 2>/dev/null || true

# Install PHP dependencies if the mounted project doesn't have them yet.
if [ ! -f vendor/autoload.php ]; then
    echo "[entrypoint] vendor/ missing — running composer install..."
    composer install --no-interaction --no-progress
fi

exec "$@"
