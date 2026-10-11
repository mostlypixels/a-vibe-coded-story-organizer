#!/bin/sh
# Replaces the Docker database and storage/app with copies of the native ones.
# Runs as root in a one-off container with the stack down (see `make db-from-native`).
# The native files are only read.

set -e

NATIVE_DB=/app/database/database.sqlite
NATIVE_APP=/app/storage/app
DOCKER_APP="$LARAVEL_STORAGE_PATH/app"

if [ -z "$DB_DATABASE" ] || [ -z "$LARAVEL_STORAGE_PATH" ]; then
    echo "ERROR: DB_DATABASE and LARAVEL_STORAGE_PATH must be set." >&2
    exit 1
fi

if [ ! -f "$NATIVE_DB" ]; then
    echo "ERROR: no native database at database/database.sqlite." >&2
    exit 1
fi

mkdir -p "$(dirname "$DB_DATABASE")" "$DOCKER_APP/backups"

# Keep the old Docker data in a snapshot, because the copy replaces it.
if [ -f "$DB_DATABASE" ]; then
    echo "Saving a snapshot of the current Docker database..."
    php artisan db:backup --name=before-native-copy --path="$DOCKER_APP/backups"
fi

# `.backup` gives one consistent file, even when the native file has -wal/-shm files.
echo "Copying the native database..."
rm -f "$DB_DATABASE" "$DB_DATABASE-wal" "$DB_DATABASE-shm"
sqlite3 -readonly "$NATIVE_DB" ".backup '$DB_DATABASE'"

# Replace, not merge. Backups stay: they hold the snapshot above.
echo "Copying storage/app (without backups and exports)..."
find "$DOCKER_APP" -mindepth 1 -maxdepth 1 ! -name backups -exec rm -rf {} +
find "$NATIVE_APP" -mindepth 1 -maxdepth 1 ! -name backups ! -name exports -exec cp -a {} "$DOCKER_APP/" \;

chown -R laravel:laravel "$(dirname "$DB_DATABASE")" "$LARAVEL_STORAGE_PATH"
echo "Done."
