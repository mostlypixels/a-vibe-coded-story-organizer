# Docker database volume — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

An exception log: a task that went to plan gets no entry, because the diff and the task
file already record what was built. Bullets under the headings below, root cause first.

## Feedback & decisions

- 2026-10-11 grill: separate storage folder `storage-docker/` at the repo root; first Docker data comes from one `db-from-native` run; copies native → Docker only, plus `db-to-host`; copy `storage/app` without backups and exports; replace, not merge.
- `clear_env = no` in the PHP image's `docker.conf`, so FPM sees `LARAVEL_STORAGE_PATH`. Checked in the running container.

## Deviations from the spec/plan

- `db-from-native` logic sits in `docker/db-from-native.sh`, not in a Makefile `sh -c '...'`. Single quotes do not quote under `cmd.exe`. The recipe runs the script with `run --rm --no-deps --entrypoint sh app`.
- `db-from-native` ends with `up -d`, not `up`: attached `up` would block the shell after the copy.
- `clean` runs `stop`, a one-off `rm -rf /app/storage-docker` container, then `down -v`. Needs no shell syntax, and removes root-owned files.

## Issues → resolutions

- `Dockerfile.dev` COPYs `docker/entrypoint.sh` into the image, so `make down` + `make up` still ran the old entrypoint: `storage-docker/` had no skeleton, was root-owned, and `/` answered 500. Fix: `docker compose -f docker-compose.dev.yml build` first. Then the container was healthy.
- `make up` runs `docker compose up` attached, so it blocks the calling shell. Use `docker compose -f docker-compose.dev.yml up -d` in automated checks.
- The Git Bash `composer` wrapper passes `/c/...` to Windows PHP, so `verify.sh` fails with "Could not open input file". Not a repo bug. Workaround: a `composer` shim earlier on PATH that runs `php C:/laragon/bin/composer/composer.phar "$@"`.
- `make clean` still deletes native `database/database.sqlite` (`REMOVE_DEV_DATABASE`) until task 02 fixed it (done). The Docker docs already say `clean` removes only the volume.
- A full `db-from-native` takes about 9 minutes (749 MB of media through the Windows bind mount), and the next start is slow because the entrypoint `chown -R`s `storage-docker/`. Wait for health before checking `/`.
- `assets-state.sh` fails on `public/hot` while the Docker Vite server runs. Expected, not a defect.
