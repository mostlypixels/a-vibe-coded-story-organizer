---
title: "Task 02 — Make commands"
---

# Task 02 — Make commands

## Scope

- `clean`: drop `$(REMOVE_DEV_DATABASE)` and its definition (it deletes the native database). Remove `storage-docker/` instead, from inside a container or with a portable recipe.
- `db-to-host`: `db:backup --name=docker --path=/app/storage-docker/app/backups`. Works while the stack runs.
- `db-from-native`: `guard-native`, `down`, then a one-off `run --rm` container that:
  1. snapshots the current Docker database with `db:backup` (skip when the file is missing);
  2. replaces `/var/lib/imagoldfish/database.sqlite` with `/app/database/database.sqlite`;
  3. empties `storage-docker/app` and copies `storage/app` without `backups/` and `exports/`;
  4. fixes ownership.
  Then `up`. `migrate` runs in the entrypoint.
- `help` lists both new targets. Add them to `.PHONY`.
- `documentation/development/docker.md`: Commands table (`clean` resets Docker data only, the two new targets), a short "Moving data into Docker" section.
- `CHANGELOG.md`: one dated section.

## Depends on

01.

## Key decisions already made

- Native → Docker only. No reverse copy.
- Replace, not merge: no stray files from the old Docker data.
- The backup in step 1 stays in `storage-docker/app/backups`, outside the emptied folders. Empty everything in `storage-docker/app` except `backups/`.
- Native SQLite may hold `-wal`/`-shm` files. Copy with `sqlite3 .backup` or `VACUUM INTO` from the native file, not `cp`. `guard-native` blocks the native server; native scripts are the user's job.

Detail: `expanded/architecture.md` → *Makefile*.

## Tests

None automated: PHPUnit cannot run Make recipes.

Manual, from `expanded/testing.md` steps 1–7. Then run `make db-from-native` for real: Gardenia Girl and Les Misérables show their covers and codex images in Docker. Time the #298 query and note the number in the PR.
