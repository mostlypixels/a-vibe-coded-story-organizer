# Database backups

[Documentation](../README.md) / [Development](README.md) / Database backups

The whole app is one file: `database/database.sqlite`. `php artisan db:backup` writes a snapshot of it and deletes older snapshots of the same name.

## What it does

- Writes `<name>-<Y-m-d_His>.sqlite`, for example `before-migrate-2026-09-24_164211.sqlite`.
- Uses `VACUUM INTO`. The snapshot is consistent while the app runs, and the live file does not change.
- Keeps the newest snapshots **per name**. A scheduled run never deletes a `before-migrate` snapshot.
- Refuses a second run with the same name in the same second.
- Fails with a non-zero exit when the connection is not SQLite or the directory is not writable.

| Option | Default | Purpose |
|---|---|---|
| `--name=` | `scheduled` (`BACKUP_DEFAULT_NAME`) | Letters, digits, `-` and `_` only |
| `--keep=` | 48 (`BACKUP_KEEP`) | Snapshots of this name to keep |
| `--path=` | `storage/app/backups` (`BACKUP_PATH`) | Destination directory |
| `--dry-run` | off | Report only; write and delete nothing |

## Schedule

- `routes/console.php` runs `db:backup` every `BACKUP_EVERY_HOURS` hours (default 1), at minute 0.
- Use 1 to 24. Another value stops every artisan command with an error. A value that does not divide 24 gives a shorter gap before midnight.
- The scheduler runs the job. The Docker stack (`make up`, production) and `composer dev` both start `php artisan schedule:work`.

> [!WARNING]
> A plain `php artisan serve` does not run the scheduler. Run `php artisan schedule:work` in a second terminal, or no scheduled snapshot happens.

## Snapshot before a risky step

Before a migration, an import, or `migrate:fresh`:

```bash
php artisan db:backup --name=before-migrate
# Docker:
docker compose -f docker-compose.dev.yml exec app php artisan db:backup --name=before-migrate
```

## Restore

1. Stop the app: `make down` or `bash scripts/stop-app.sh`. A running app writes to the file you replace.
2. Copy the old live file aside: `cp database/database.sqlite database/database.sqlite.broken`.
3. Copy the snapshot back: `cp storage/app/backups/<snapshot>.sqlite database/database.sqlite`.
4. Start the app again.

> [!WARNING]
> The snapshots live on the same disk as the database. A lost disk or a careless delete of `storage/` takes both. Copy the snapshots somewhere else yourself.
