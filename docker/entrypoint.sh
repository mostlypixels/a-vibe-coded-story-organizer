#!/bin/sh

set -e

echo "Starting Imagoldfish application setup..."

# Wait for Redis to be ready (development)
if [ "$APP_ENV" = "local" ]; then
    echo "Waiting for Redis..."
    until nc -z redis 6379; do
        echo "Redis is unavailable - sleeping"
        sleep 1
    done
    echo "Redis is up"
fi

# APP_KEY: in dev the whole repo (including .env) is bind-mounted, so
# `key:generate` can write a key back to .env once and it persists across
# restarts. In production there is no .env file in the image (by design —
# config comes from real environment variables), so `key:generate` has
# nothing to write to and a key silently regenerated on every restart would
# invalidate all existing sessions/encrypted data anyway. Require it there.
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:" ]; then
    if [ -f .env ]; then
        echo "Generating APP_KEY (writing to .env)..."
        php artisan key:generate --force
    else
        echo "ERROR: APP_KEY is not set and there is no .env file to generate one into." >&2
        echo "Set a persisted APP_KEY via the environment (e.g. docker-compose's" >&2
        echo "'environment:' or a secret) — run 'php artisan key:generate --show' once" >&2
        echo "to produce a key and store that value, rather than relying on this" >&2
        echo "container to generate a new one on every start." >&2
        exit 1
    fi
fi

# Run pending migrations (creates database/database.sqlite on first run)
echo "Running migrations..."
php artisan migrate --force

# Clear caches
echo "Clearing application caches..."
php artisan config:clear
php artisan cache:clear

# public/storage -> storage/app/public, so uploaded files (cover images,
# codex media) are reachable by nginx. `storage:link` no-ops if it already
# exists, so this is safe to run on every start.
if [ ! -e public/storage ]; then
    echo "Linking storage..."
    php artisan storage:link
fi

# This entrypoint runs as root (needed for the migrate/key-generate steps
# above), but php-fpm's workers run as laravel — anything just created here
# (database.sqlite, cache/log files) would otherwise be root-owned and
# unwritable by the app.
chown -R laravel:laravel database storage bootstrap/cache

# node_modules and vendor are anonymous volumes (see docker-compose.dev.yml)
# populated from the image's `RUN npm install` / `RUN composer install`
# layers, which run as root — so they start out root-owned on every fresh
# volume. Vite (run as laravel, see docker/supervisord.dev.conf) needs write
# access to node_modules/.vite for its dependency optimizer cache, otherwise
# every JS import fails with EACCES and the browser sees 404/504s.
if [ -d node_modules ]; then
    chown -R laravel:laravel node_modules
fi
if [ -d vendor ]; then
    chown -R laravel:laravel vendor
fi

echo "Setup complete!"

# Execute the main command
exec "$@"
