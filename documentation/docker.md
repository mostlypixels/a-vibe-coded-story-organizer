# Docker

Imagoldfish can run in Docker instead of a local PHP/Node install. Docker is the
supported way to run the full stack (PHP, Redis, MailHog) without installing
those services on the host machine.

> [!NOTE]
> This does not replace `composer test` / `composer lint` / `npm run build` as the
> canonical commands (see `CLAUDE.md`) — those still run the same way, just inside the
> container. Prefer the local toolchain if it's already set up; reach for Docker when it
> isn't, or to match production more closely.

## Prerequisites

- Docker Desktop (Windows/Mac) or Docker Engine + Compose (Linux)
- 4GB+ RAM allocated to Docker
- Port 8000 (dev) or 80 (prod) free

## Quick start (development)

```bash
cp .env.docker .env
make up          # builds and starts app, redis, mailhog
make migrate     # in another terminal, once the app container is up
```

- App: http://localhost:8000
- Vite (HMR): http://localhost:5173
- MailHog UI: http://localhost:8025

`make up` without a Makefile target on your platform is just
`docker-compose -f docker-compose.dev.yml up`; every `make` target in this doc is a
thin alias documented in the root `Makefile` (`make help` lists them all).

## Everyday commands

| Command | Purpose |
|---|---|
| `make up` / `make down` | start / stop the dev containers |
| `make shell` | bash inside the app container |
| `make test` | `composer test` inside the container |
| `make lint` | `composer lint` inside the container |
| `make migrate` / `make seed` / `make fresh` | database setup |
| `make tinker` | Laravel REPL |
| `make logs` | follow container logs |
| `make clean` | remove containers **and volumes** (resets the dev database) |

Anything not covered by a `make` target can be run directly:

```bash
docker-compose -f docker-compose.dev.yml exec app php artisan <command>
docker-compose -f docker-compose.dev.yml exec app npm run build
```

## Services

- **app** — PHP 8.4-FPM + Nginx (via Supervisor), built from `Dockerfile` (production)
  or `Dockerfile.dev` (adds Xdebug, Node/npm, hot-reload volume mounts).
- **redis** — cache/session store.
- **mailhog** — catches outgoing mail in development; UI at `:8025`.

The database is SQLite (`database/database.sqlite`), mounted as a volume so it
survives container restarts — `make clean` is the one command that removes it.

## Debugging (Xdebug)

Xdebug ships only in `Dockerfile.dev`, listening on port 9000. Configure your IDE
to listen for incoming debug connections on that port and map `/app` to the
project root.

## Production

Unlike development, the production image does **not** contain a `.env` file (config
comes from real environment variables instead), and it will **not** generate its own
`APP_KEY` — it refuses to start without one. This is deliberate: silently generating a
new key on every restart would invalidate all existing sessions and encrypted data. So
before the container will boot, you generate a key once and give it to the container
via the environment.

1. **Build the image:**

   ```bash
   docker build -t imagoldfish:latest .
   ```

2. **Generate an `APP_KEY`.** This is a one-off command — it doesn't need any
   containers running:

   ```bash
   docker run --rm imagoldfish:latest php artisan key:generate --show
   ```

   This prints a value like `base64:XXXXXXXX...`. Copy it.

3. **Put that value in a `.env` file next to `docker-compose.yml`** (this is a
   separate file from the app's own `.env` — it's what `docker-compose` reads to fill
   in `${...}` placeholders when starting containers):

   ```
   APP_KEY=base64:XXXXXXXX...
   ```

   > [!WARNING]
   > `APP_KEY` must be unique per deployment — never reuse the value from
   > `.env.docker` or `.env.production.example` (both ship blank/placeholder on
   > purpose), and never reuse one deployment's key for another.

4. **Start the containers and run migrations:**

   ```bash
   docker-compose up -d
   docker-compose exec app php artisan migrate
   ```

If you skip step 2–3, `docker logs a-vibe-coded-story-organizer_app` will show the
container restarting over and over with `ERROR: APP_KEY is not set` — that's this
exact situation, and setting `APP_KEY` as above is the fix.

The production image (`Dockerfile`) is a multi-stage build: frontend assets are
built in a separate stage, only production Composer dependencies are installed,
and the app runs as a non-root `laravel` user with Xdebug and dev tooling absent.
Adapt `docker-compose.yml` for your deployment target (Swarm, Kubernetes, etc.).

## Troubleshooting

- **Port already in use** — set `APP_PORT` in `.env` and restart (`make down && make up`).
- **Container won't start** — `make logs` (dev) or
  `docker logs a-vibe-coded-story-organizer_app` (prod) for the actual error. A crash
  loop with `ERROR: APP_KEY is not set` means you skipped the `APP_KEY` setup in the
  [Production](#production) section above.
- **Database not persisting** — ensure the `database/` directory exists on the host
  (`mkdir -p database`) before `make up`.
- **Permission errors on `storage`/`bootstrap/cache`** — the app runs as the
  `laravel` (UID 1000) user; `chmod -R 775 storage bootstrap/cache` on the host if
  volumes were created with a different owner.
- **Slow on Mac/Windows** — allocate more CPU/RAM to Docker Desktop; `vendor/` and
  `node_modules/` are already excluded from the bind mount in `docker-compose.dev.yml`
  to reduce sync overhead.

## File map

```
Dockerfile                 # production image
Dockerfile.dev              # development image (Xdebug, Node/npm)
docker-compose.yml          # production services
docker-compose.dev.yml      # development services
.dockerignore
.env.docker                  # dev environment template — copy to .env
.env.production.example      # production environment template
Makefile                     # `make` shortcuts, see `make help`
docker/
  entrypoint.sh              # APP_KEY generation, migrations, cache clear on boot
  nginx.conf
  php.ini
  supervisord.conf
```
