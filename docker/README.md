# `docker/` — container configuration

Config files consumed by the images defined in the root `Dockerfile` /
`Dockerfile.dev`:

- `entrypoint.sh` — container boot: generates `APP_KEY` if unset, runs pending
  migrations, clears config/cache.
- `nginx.conf` — serves the Laravel app (production image only).
- `supervisord.conf` — runs PHP-FPM and Nginx as sibling processes.
- `php.ini` — upload limits, memory limits, logging.

For everything else — quick start, `make` commands, services, troubleshooting,
production deployment — see [`documentation/docker.md`](../documentation/docker.md).
