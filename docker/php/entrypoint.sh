#!/bin/sh
set -e

# Ensure runtime-writable directories exist and are writable regardless of the
# host UID of the bind-mounted project (local-dev convenience).
mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache
chmod -R 777 storage bootstrap/cache 2>/dev/null || true

# Install PHP dependencies if the mounted project doesn't have them yet.
if [ ! -f vendor/autoload.php ]; then
    echo "[entrypoint] vendor/ missing — running composer install..."
    composer install --no-interaction --no-progress
fi

exec "$@"
