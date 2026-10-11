# Overview

Docker and native share `database/database.sqlite` and `storage/` today. The database on the Windows bind mount is about 40 times slower than a copy in the container (#298). Two runtimes that write one file also corrupted it on 2026-10-10.

## Decisions

- Docker's database goes into a named volume. Native keeps `database/database.sqlite`.
- Docker gets its own storage tree, `storage-docker/`, on the bind mount. Native keeps `storage/`. Laravel's `LARAVEL_STORAGE_PATH` moves the whole tree with one variable, so each runtime only reads, deletes and purges its own images, imports, exports, backups and logs.
- Data moves between runtimes only through copy commands. A copy duplicates the image files, so two databases never point to one file.

## Goals

- Docker queries, sessions, cache and queue run at in-container speed.
- No shared write target between runtimes. A deletion or purge in one runtime cannot remove the other runtime's files.
- Docker images, backups and logs stay visible in Explorer.

## Non-goals

- Images in a volume. Image reads are one file access each; measure before moving them.
- Two-way sync. Copies are whole and one-way, and they overwrite the target.
- Production `docker-compose.yml` and `Dockerfile`.
- Session or cache drivers other than `database`.

## Acceptance criteria

- In `make shell`, `php artisan tinker` reports a `DB_DATABASE` outside `/app` and `storage_path()` = `/app/storage-docker`.
- The #298 recent-scenes query takes under 10 ms in Docker on the Les Misérables project.
- A codex image uploaded in Docker lands in `storage-docker/app/private/media/`. A native upload lands in `storage/app/private/media/`.
- `make fresh` and `make clean` reset only Docker data. Native data is untouched.
- `make db-from-native` copies the native database and native `storage/app` into Docker. The copied project shows its covers and codex images.
- `make db-to-host` writes a consistent snapshot of the Docker database to the host for the IDE.
- `documentation/development/docker.md` no longer says the runtimes share a database.
