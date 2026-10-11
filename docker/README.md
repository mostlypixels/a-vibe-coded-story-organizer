# `docker/` — container configuration

Config files consumed by the images defined in the root `Dockerfile` /
`Dockerfile.dev`:

- `entrypoint.sh` — container boot: generates `APP_KEY` if unset, runs pending
  migrations, clears config/cache.
- `db-from-native.sh` — one-off copy of the native database and `storage/app`
  into Docker; run by `make db-from-native`.
- `nginx.conf` — serves the Laravel app (production image only).
- `supervisord.conf` — runs PHP-FPM, Nginx, the queue worker, and the scheduler
  as sibling processes.
- `php.ini` — upload limits, memory limits, logging.

For everything else — quick start, `make` commands, services, troubleshooting,
production deployment — see [Docker](../documentation/development/docker.md).
