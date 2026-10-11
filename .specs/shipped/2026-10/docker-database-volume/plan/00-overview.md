# Docker database volume — plan

## Order

1. `01-volume-and-storage-root` — Docker database in a named volume, Docker storage in `storage-docker/`.
2. `02-make-commands` — `clean` fix, `db-to-host`, `db-from-native`, docs and changelog.

## Binding decisions (grill, 2026-10-11)

- Docker gets its own storage folder via `LARAVEL_STORAGE_PATH=/app/storage-docker`, at the repo root, not under `storage/`.
- PHP-FPM passes env vars: the base image's `docker.conf` sets `clear_env = no`. No FPM change.
- Copies go native → Docker only (`db-from-native`), plus a snapshot `db-to-host`. No Docker → native copy.
- `db-from-native` copies the database and `storage/app` without `backups/` and `exports/`. It replaces Docker's data, it does not merge.
- `db-from-native` runs with the stack down, in a one-off container.
- After task 02, run `make db-from-native` once to bring Gardenia Girl and Les Misérables into Docker.
- `public/storage` stays one shared link. No change.

## Invariants

- Native paths do not change: `database/database.sqlite`, `storage/`.
- No Docker command writes the native database or `storage/`.
- No app code changes. Config, Compose, entrypoint, Makefile, docs.
- Makefile recipes run under `cmd.exe` and `sh`. Put shell logic inside the container.
