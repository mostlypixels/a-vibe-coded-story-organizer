# Architecture

No app code changes. Config, Compose, entrypoint, Makefile and docs only.

## Layout

| | Native | Docker |
|---|---|---|
| Database | `database/database.sqlite` (host) | `/var/lib/imagoldfish/database.sqlite` (named volume `dbdata`) |
| Storage root | `storage/` | `/app/storage-docker` = `storage-docker/` (bind mount) |
| Compiled views | `storage/framework/views` | `/tmp/views` (unchanged) |

Everything that uses `storage_path()` follows the root: the `local`, `media` and `public` disks, `backup.path`, `exports.temp_path`, import working files, logs, `HtmlSanitizer`'s cache, `public/storage` link target.

## `docker-compose.dev.yml`

```yaml
environment:
  - DB_DATABASE=/var/lib/imagoldfish/database.sqlite
  - LARAVEL_STORAGE_PATH=/app/storage-docker
volumes:
  - dbdata:/var/lib/imagoldfish
# top level
volumes:
  dbdata:
```

- A real env var wins over `.env`, as with `VIEW_COMPILED_PATH`.
- `Application::storagePath()` reads `LARAVEL_STORAGE_PATH` from `$_ENV` or `$_SERVER`, not `getenv()`. Check that PHP-FPM passes it (`clear_env`). If not, set it in the FPM pool config.

## `docker/entrypoint.sh`

Before `migrate`:

- Create the database directory if missing. `migrate --force` creates the file, as it does today.
- Create the storage skeleton: `app/private/media`, `app/public`, `framework/{cache/data,sessions,testing}`, `logs`.
- Add `/var/lib/imagoldfish` and `$LARAVEL_STORAGE_PATH` to the `chown`.

`storage:link` already runs only when `public/storage` is missing. Its target follows the storage root.

## Makefile

| Target | Change |
|---|---|
| `fresh*` | No change: `migrate:fresh` runs in the container on the volume. |
| `clean` | `down -v` already removes `dbdata`. Drop `REMOVE_DEV_DATABASE`: it deletes the native database. Also remove `storage-docker/`. |
| `db-to-host` (new) | `db:backup --name=docker --path=/app/storage-docker/app/backups`. The snapshot uses `VACUUM INTO`, so it is safe while the app runs. |
| `db-from-native` (new) | Stop the app process, copy `database/database.sqlite` into the volume, mirror `storage/app/` into `storage-docker/app/`, migrate, start. Refuses while the native server runs (`guard-native`). Takes a `db:backup` of the Docker database first. |

- Recipes must work in `cmd.exe` and `sh`. Do the copies inside the container (`$(APP_EXEC) sh -c '…'`): `/app/database` and `/app/storage` are visible there through the bind mount.
- The native database file must not be open in a writer during the copy. `guard-native` covers the native server; native scripts are on the user.

## Repo files

- `.gitignore`: `/storage-docker`.
- `.dockerignore`: `storage-docker` (keeps it out of the production build context).

## Docs

`documentation/development/docker.md`:

- Development start warning: keep the port-8000 conflict, drop the shared-database reason.
- Services: the volume, the storage root, and why (#298 numbers).
- Commands table: `db-to-host`, `db-from-native`; `clean` resets Docker data only.
- Troubleshooting "Database does not persist": now about the volume.

Also: `scripts/serve-app.sh` and the Makefile header comments that say both runtimes write one file. Native `storage/` and `database/` stay where they are, so native docs do not change.
