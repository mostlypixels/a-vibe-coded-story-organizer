# Testing

## Automated

Extend `tests/Feature/DockerComposeDevTest.php`. It reads `docker-compose.dev.yml` as text, like the `VIEW_COMPILED_PATH` check.

- `DB_DATABASE` is set and does not start with `/app`.
- `LARAVEL_STORAGE_PATH` is set and is not `/app/storage`.
- The `DB_DATABASE` directory is mounted from a named volume, not a bind path.

Add a `.gitignore` check: `storage-docker` is ignored.

No PHPUnit test can run the entrypoint or Make recipes. Cover those by hand.

## Manual, in Docker

1. `make clean`, `make up`. The app starts on an empty volume; `storage-docker/` gets its skeleton.
2. `make fresh`. Native `database/database.sqlite` keeps its modified time.
3. Upload a codex image. It lands under `storage-docker/`, and nothing changes under `storage/`.
4. Time the #298 query (as in the issue). Target: under 10 ms.
5. `make db-from-native`. The native projects show their covers and codex images in Docker. Delete one codex image in Docker; the native file stays.
6. `make db-to-host`. Open the snapshot in PhpStorm.
7. Run the native server after `make down`. It still sees its own data and images.

## Pitfalls

- Run the full `verify.sh` natively. Its in-memory SQLite is not affected.
- `RefreshDatabase` tests in `make test` use in-memory SQLite, so the volume does not affect them.
