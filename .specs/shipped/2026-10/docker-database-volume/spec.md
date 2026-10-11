---
status: shipped
shipped: 2026-10-11
planned: 2026-10-11
expanded: 2026-10-11
---

# Docker Database Volume

In Docker on Windows, `database/database.sqlite` sits on the bind mount. Each query pays for file access through the mount: 196 ms against 4.7 ms for a copy inside the container (#298). Sessions, cache and queue use the `database` driver, so every request pays too (about 25 ms for the session write).

## Goals

- The Docker dev database lives in a named volume. Queries, sessions, cache and queue get fast.
- The native server keeps `database/database.sqlite`. Docker and native can no longer write to one file (the 2026-10-10 corruption).
- Images stay on the bind mount, visible in Explorer. The two databases never delete each other's files.
- A simple way to copy the Docker database out (for the IDE) and in (to restore).

## Non-goals

- Moving `storage/` into a volume. Wait for a measurement that shows image reads are slow.
- Syncing data between Docker and native. Use backup and import, or the copy commands.
- The production `docker-compose.yml`.
- Moving sessions or cache off the database driver.

## Image storage

Each database stores only paths. The files are on the shared `storage/` bind mount.

- **No clash on write.** `store()` and `putFile()` give each file a random 40-character name. Seeders write no media.
- **Risk 1: one file, two databases.** When a database file is copied from one runtime to the other, both databases point to the same image files. If you delete a codex entry or project in one runtime, its files are gone in the other as well.
- **Risk 2: orphan purge.** `imports:purge` → `purgeOrphanedFiles()` deletes import files that have no row in *its* database. Docker can delete an unfinished native import, and native can delete an unfinished Docker import.

Proposed fix: one storage root per runtime, chosen by an env var like `VIEW_COMPILED_PATH`.

- Docker: the `local`, `media` and `public` disks point under `storage/app/docker/`. Native keeps `storage/app/`.
- Both trees stay on the bind mount and visible in Explorer.
- Copying a database across runtimes also needs its storage tree, so the copy commands move both.
- Risks 1 and 2 go away: each runtime only reads, deletes and purges its own tree.

## Rough approach

- `docker-compose.dev.yml`: add a named volume, set `DB_DATABASE` to a path inside it, and set the storage-root env var.
- `docker/entrypoint.sh`: create and migrate the database if missing, and create the Docker storage tree.
- `config/filesystems.php`, `config/backup.php`, `config/exports.php`: read the storage root from env. Native defaults stay the same.
- Makefile: point `fresh` and `clean` at the volume. Add copy-out and copy-in targets that move the database and its storage tree together.
- Backups (`db:backup`) stay on the bind mount, so they are visible on the host.
- Docs: `documentation/development/docker.md`. The "never run both" rule keeps the port-8000 conflict but drops the corruption reason.

## Open questions

- Is a per-runtime storage root worth it, or do we only document risks 1 and 2? Today you mostly use Docker.
- Does the `public/storage` link still work with a moved `public` disk root, or does `storage:link` need the new target?
- Do logs (`storage/logs`) stay shared?
- Migrate today's Docker data into the volume once, or start fresh (`make fresh` plus re-import)?
