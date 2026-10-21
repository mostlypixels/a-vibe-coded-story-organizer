# Docker

[Documentation](../README.md) / [Development](README.md) / Docker

Docker runs the PHP, Nginx, and Node stack without a host installation. Use the same canonical commands inside or outside the container.

## Prerequisites

- Docker Desktop, or Docker Engine with Compose;
- at least 4 GB of Docker memory;
- free port 8000 for development or port 80 for production;
- optional `make` support.

The `Makefile` only wraps Docker Compose commands. Run `make help` to list them. On Windows, Make recipes use `cmd.exe`; keep new recipes portable.

## Development start

> [!WARNING]
> Stop the native server first (`bash scripts/stop-app.sh`). Both serve port 8000.
> `make up` refuses while the native PID file exists.

```bash
cp .env.docker .env
make up
make migrate
```

Run `make migrate` after the app container starts.

- App: <http://localhost:8000>
- Vite: <http://localhost:5173>
- Mailpit: <http://localhost:8025>

Without Make, use `docker compose -f docker-compose.dev.yml up`.

> [!WARNING]
> Run `make rebuild` after `composer.json` or `package.json` changes. It renews the anonymous `vendor` and `node_modules` volumes. A normal image rebuild can reuse old dependencies.

## Commands

| Command | Purpose |
|---|---|
| `make up` / `make down` | Start or stop development containers. |
| `make rebuild` | Rebuild and renew dependency volumes. |
| `make shell` | Open a shell in the app container. |
| `make test` | Run `composer test`. |
| `make lint` | Run `composer lint`. |
| `make analyse` | Run `composer analyse`. |
| `make migrate` / `make seed` / `make fresh` | Prepare the database. `seed` and `fresh` add the admin, the Melusine demo, and the second user. |
| `make demo` | Install the Melusine demo projects for the first user only. |
| `make tinker` | Open the Laravel REPL. |
| `make logs` | Follow container logs. |
| `make db-to-host` | Save a snapshot of the Docker database to `storage-docker/app/backups`. Works while the stack runs. |
| `make db-from-native` | Replace Docker data with the native database and `storage/app`. See [Moving data into Docker](#moving-data-into-docker). |
| `make clean` | Remove containers, the `dbdata` volume, and `storage-docker/`. The native database and `storage/` stay. |

Run other commands directly:

```bash
docker compose -f docker-compose.dev.yml exec app php artisan <command>
docker compose -f docker-compose.dev.yml exec app npm run build
```

## Services

- **app** runs PHP-FPM, Nginx, the queue worker, and the scheduler (`schedule:work`) through Supervisor. `Dockerfile.dev` also adds Node, Vite, Xdebug, and source mounts.
- **mailpit** catches development email.

The stack uses SQLite, split from the native runtime:

- **Database:** the named volume `dbdata`, at `/var/lib/imagoldfish/database.sqlite` (`DB_DATABASE`). It survives restarts. `make clean` removes it.
- **Why:** on the Windows bind mount a query took 196 ms against 4.7 ms inside the container (#298).
- **Storage root:** `storage-docker/` in the repo root (`LARAVEL_STORAGE_PATH=/app/storage-docker`). It stays on the bind mount, so Explorer shows images, backups, and logs. The entrypoint creates its folders.
- **Native:** keeps `database/database.sqlite` and `storage/`. Each runtime only reads, deletes, and purges its own files, so the two databases never point at one image file.
- **Entrypoint changes:** `docker/entrypoint.sh` is copied into the image. Run `docker compose -f docker-compose.dev.yml build` after you edit it.

Compiled Blade views go to `/tmp/views` inside the container, not to the bind mount (`VIEW_COMPILED_PATH`). On Windows, the mount made each view file access slow: large pages took 10 times longer. The native server does not see this variable, so it keeps `storage/framework/views`.

Cache, sessions, and queues use the database. There is no Redis service. Add Redis only when deployment needs justify another service.

## Imports and the queue worker

`IMPORT_RUN_IN_BACKGROUND=true` is set in both Compose files. A project import runs in the queue worker, not in the upload request, so a long import cannot hit the web timeout.

- **Job limits:** `ProjectImportJob` sets `tries = 1` and its own timeout from `IMPORT_TIMEOUT_SECONDS` (1800 s). They override the worker command (`--tries=3`).
- **`pcntl`:** both Dockerfiles install it. The worker enforces the job timeout only with `pcntl`. Native Windows PHP has none; native imports run inline.
- **`retry_after`:** `DB_QUEUE_RETRY_AFTER=1860` must stay above the job timeout. Otherwise a second worker takes the job while the first still runs.
- **Web timeout:** `fastcgi_read_timeout 300s` in `docker/nginx.conf` matches `max_execution_time`. The nginx default of 60 s cut off an inline import that PHP still allowed.

> [!WARNING]
> Rebuild once after you pull this change: `make rebuild`, then recreate the container. A running container keeps its old image (no `pcntl`) and its old environment.

## Moving data into Docker

Copy the native data into Docker once, to work on your real projects there:

```bash
bash scripts/stop-app.sh   # when the native server runs
make db-from-native
```

- It stops the stack, copies, and starts the stack again in the background.
- It **replaces** the Docker database and `storage-docker/app`. It does not merge.
- It copies `storage/app` without `backups/` and `exports/`.
- Before the copy, it saves the old Docker database as `before-native-copy-*.sqlite` in `storage-docker/app/backups`.
- It only reads the native files. There is no copy from Docker to native; use `make db-to-host` for a snapshot.
- A large `storage/app` takes minutes: the copy runs through the Windows bind mount.

## Xdebug

Xdebug exists only in `Dockerfile.dev`. The container connects to the IDE on the host.

1. Listen for PHP debug connections on port 9003.
2. Map `/app` to the project root and use IDE key `PHPSTORM`.
3. Trigger debugging with a browser helper, `?XDEBUG_TRIGGER=1`, or `XDEBUG_TRIGGER=1 php artisan <command>` inside `make shell`.

`xdebug.start_with_request=trigger` prevents every request and test from waiting for an IDE connection. If breakpoints fail, set `xdebug.log_level=7` in `docker/xdebug.ini`, rebuild, and inspect `make logs`.

## Production

The production image needs an external `APP_KEY`. It does not generate one because a new key would invalidate sessions and encrypted data.

1. Build the image:

   ```bash
   docker build -t imagoldfish:latest .
   ```

2. Generate a key:

   ```bash
   docker run --rm imagoldfish:latest php artisan key:generate --show
   ```

3. Put the result in the Compose environment file:

   ```text
   APP_KEY=base64:XXXXXXXX...
   ```

4. Start the service and migrate:

   ```bash
   docker compose up -d
   docker compose exec app php artisan migrate
   ```

> [!WARNING]
> Use a unique `APP_KEY` for each deployment. Do not reuse a template value or another deployment's key.

The production image uses multiple build stages. It copies built frontend assets and production Composer dependencies into a non-root runtime image. It excludes Xdebug and development tools.

## Troubleshooting

- **Port conflict:** set `APP_PORT` in `.env`, then restart.
- **Second clone shares the database:** set `COMPOSE_PROJECT_NAME` in its `.env`. The clone then gets its own containers and `dbdata` volume.
- **Start failure:** inspect `make logs` or `docker compose logs app`. An `APP_KEY is not set` loop means production key setup is incomplete.
- **Database does not persist:** the data is in the `dbdata` volume. `make clean` or `docker compose -f docker-compose.dev.yml down -v` removes it. Check `docker volume ls` for `dbdata`.
- **Storage permission errors:** the image uses user `laravel` with UID 1000. Correct ownership or grant group write access to `storage` and `bootstrap/cache`.
- **Style changes appear late:** Vite polls bind mounts every 60 seconds on Windows and macOS. Wait or run `make restart`.
- **Slow desktop Docker:** allocate more CPU and memory. Dependency directories already use anonymous volumes to reduce sync work.

## File map

```text
Dockerfile                    Production image
Dockerfile.dev                Development image
docker-compose.yml            Production services
docker-compose.dev.yml        Development services
db-from-native.sh             Copy native data into Docker (make db-from-native)
storage-docker/               Docker storage root (ignored by Git)
.env.docker                   Development environment template
.env.production.example       Production environment template
Makefile                      Command shortcuts
docker/entrypoint.sh          Startup checks and preparation
docker/nginx.conf             Nginx configuration
docker/php.ini                Shared PHP settings
docker/xdebug.ini             Development debugger settings
docker/supervisord.conf       Production processes
docker/supervisord.dev.conf   Development processes
```
