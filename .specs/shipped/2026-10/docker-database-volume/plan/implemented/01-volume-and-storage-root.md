---
title: "Task 01 — Volume and storage root"
---

# Task 01 — Volume and storage root

## Scope

- `docker-compose.dev.yml`: named volume `dbdata` at `/var/lib/imagoldfish`. Env `DB_DATABASE=/var/lib/imagoldfish/database.sqlite`, `LARAVEL_STORAGE_PATH=/app/storage-docker`. Comment the why (#298), like the `VIEW_COMPILED_PATH` comment.
- `docker/entrypoint.sh`: before `migrate`, create the database directory and the storage skeleton. Add both to the `chown`. Fix the "creates database/database.sqlite" comment.
- `.gitignore`: `/storage-docker`. `.dockerignore`: `storage-docker`.
- `documentation/development/docker.md`: Development start warning (port only), Services (volume, storage root, why), Troubleshooting "Database does not persist".
- Comments that say both runtimes write one file: `scripts/serve-app.sh`, the Makefile header. Keep the port reason.

Deferred to task 02: Makefile targets, `clean`, CHANGELOG.

## Depends on

Nothing.

## Key decisions already made

- Skeleton: `app/private/media`, `app/public`, `framework/cache/data`, `framework/sessions`, `framework/testing`, `logs`.
- `migrate --force` creates the database file. The entrypoint only makes the directory.
- Production Compose and `Dockerfile` are untouched.

Detail: `expanded/architecture.md` → *Layout*, *docker-compose.dev.yml*, *docker/entrypoint.sh*, *Repo files*.

## Tests

In `tests/Feature/DockerComposeDevTest.php`, text checks like the existing one:

- `DB_DATABASE` is set and does not start with `/app`.
- `LARAVEL_STORAGE_PATH` is set and is not `/app/storage`.
- A named volume is mounted on the `DB_DATABASE` directory.
- `.gitignore` lists `/storage-docker`.

Manual: `make down`, `make up`. In `make shell`, `php artisan tinker`: `storage_path()` is `/app/storage-docker`, `config('database.connections.sqlite.database')` is in the volume. App loads; native `database/database.sqlite` modified time is unchanged.
