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
> Stop the native server first (`bash scripts/stop-app.sh`). Both serve port 8000
> and write `database/database.sqlite`; two writers on one SQLite file corrupt it
> across the bind mount. `make up` refuses while the native PID file exists.

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
| `make migrate` / `make seed` / `make fresh` | Prepare the database. |
| `make tinker` | Open the Laravel REPL. |
| `make logs` | Follow container logs. |
| `make clean` | Remove containers and volumes, including development data. |

Run other commands directly:

```bash
docker compose -f docker-compose.dev.yml exec app php artisan <command>
docker compose -f docker-compose.dev.yml exec app npm run build
```

## Services

- **app** runs PHP-FPM and Nginx through Supervisor. `Dockerfile.dev` also adds Node, Vite, Xdebug, and source mounts.
- **mailpit** catches development email.

The stack uses SQLite. The database directory is mounted so data survives restarts. `make clean` removes it.

Cache, sessions, and queues use the database. There is no Redis service. Add Redis only when deployment needs justify another service.

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
- **Start failure:** inspect `make logs` or `docker logs a-vibe-coded-story-organizer_app`. An `APP_KEY is not set` loop means production key setup is incomplete.
- **Database does not persist:** create the host `database/` directory before startup.
- **Storage permission errors:** the image uses user `laravel` with UID 1000. Correct ownership or grant group write access to `storage` and `bootstrap/cache`.
- **Style changes appear late:** Vite polls bind mounts every 60 seconds on Windows and macOS. Wait or run `make restart`.
- **Slow desktop Docker:** allocate more CPU and memory. Dependency directories already use anonymous volumes to reduce sync work.

## File map

```text
Dockerfile                    Production image
Dockerfile.dev                Development image
docker-compose.yml            Production services
docker-compose.dev.yml        Development services
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
