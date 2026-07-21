# Docker

Imagoldfish can run in Docker instead of a local PHP/Node install. Docker is the
supported way to run the full stack (PHP, Nginx, Node) without installing those
on the host machine.

> [!NOTE]
> This does not replace `composer test` / `composer lint` / `npm run build` as the
> canonical commands (see `CLAUDE.md`) — those still run the same way, just inside the
> container. Prefer the local toolchain if it's already set up; reach for Docker when it
> isn't, or to match production more closely.

## Prerequisites

- Docker Desktop (Windows/Mac) or Docker Engine + Compose (Linux)
- 4GB+ RAM allocated to Docker
- Port 8000 (dev) or 80 (prod) free
- `make`, optionally — every `make` target is a one-line alias, so you can always
  read the `Makefile` and run the underlying `docker compose ...` command instead.
  Windows has no `make` by default; `winget install ezwinports.make` provides
  GNU Make 4.x (the `GnuWin32.Make` package is version 3.81 from 2006 — avoid it).

> [!NOTE]
> On Windows, Make runs each recipe through `cmd.exe`, not a POSIX shell, so Unix
> commands like `rm` are unavailable inside a recipe. `make clean` therefore switches
> on the `OS` variable to use `del` there. Keep that in mind before adding shell
> commands to a target — a recipe that works on macOS may fail on Windows.

## Quick start (development)

```bash
cp .env.docker .env
make up          # builds and starts app + mailpit
make migrate     # in another terminal, once the app container is up
```

- App: http://localhost:8000
- Vite (HMR): http://localhost:5173
- Mailpit UI: http://localhost:8025

`make up` without a Makefile target on your platform is just
`docker compose -f docker-compose.dev.yml up`; every `make` target in this doc is a
thin alias documented in the root `Makefile` (`make help` lists them all).

> [!WARNING]
> After changing `composer.json` or `package.json` — or pulling a branch that did —
> run **`make rebuild`**, not `make build && make up`. `vendor/` and `node_modules/`
> live in anonymous volumes, and Compose reuses an existing anonymous volume when it
> recreates a container, so a plain rebuild leaves the *old* dependencies mounted
> over your new image. The symptom is a build that appears to succeed and change
> nothing. `make rebuild` passes `--renew-anon-volumes`, which is the fix.

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
docker compose -f docker-compose.dev.yml exec app php artisan <command>
docker compose -f docker-compose.dev.yml exec app npm run build
```

## Services

- **app** — PHP-FPM + Nginx (via Supervisor), built from `Dockerfile` (production)
  or `Dockerfile.dev` (adds Xdebug, Node/npm, hot-reload volume mounts).
- **mailpit** — catches outgoing mail in development; UI at `:8025`. Dev only.

There is deliberately **no Redis container**. Cache, sessions, and the queue all use
the database (`CACHE_STORE=database`, `SESSION_DRIVER=database`,
`QUEUE_CONNECTION=database`), whose tables ship with Laravel's default migrations —
so a second service would have bought nothing at this scale. If a deployment
outgrows that, add Redis back and set `CACHE_STORE=redis` plus the `REDIS_*`
variables that `config/database.php` already reads.

The database is SQLite (`database/database.sqlite`), mounted as a volume so it
survives container restarts — `make clean` is the one command that removes it.

## Debugging (Xdebug)

Xdebug ships only in `Dockerfile.dev`, configured by `docker/xdebug.ini`.

The direction matters and is the usual source of confusion: **the container connects
out to your IDE**, which listens on your machine. Nothing is published from the
container for debugging, and no port mapping is needed.

To debug:

1. In your IDE, start listening for PHP debug connections on port **9003** (the
   Xdebug 3 default — Xdebug 2's 9000 also collided with php-fpm's own port).
2. Map the remote path `/app` to your project root, and set the IDE key to
   `PHPSTORM`.
3. Trigger a session. `xdebug.start_with_request=trigger` means Xdebug only attaches
   when asked — use a browser extension ("Xdebug helper"), or append
   `?XDEBUG_TRIGGER=1` to the URL. For a CLI command:
   `make shell` then `XDEBUG_TRIGGER=1 php artisan <command>`.

> [!NOTE]
> `trigger` is deliberate. With `xdebug.start_with_request=yes`, *every* request and
> every test would try to open a connection back to the IDE and stall until it timed
> out, making `composer test` noticeably slower whenever you aren't debugging.

If breakpoints never hit, set `xdebug.log_level=7` in `docker/xdebug.ini`, rebuild,
and check the php-fpm log via `make logs` for Xdebug's own diagnostics.

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
   docker compose up -d
   docker compose exec app php artisan migrate
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
  php.ini                    # shared PHP settings (both images)
  xdebug.ini                 # dev image only — step-debugging config
  supervisord.conf
```
