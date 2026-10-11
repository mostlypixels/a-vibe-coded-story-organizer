---
title: "Task 03 — Queue and Docker"
---

# Task 03 — Queue and Docker

## Scope

- `ProjectImportJob`:
  - `$tries = 1`, `timeout()` from `config('import.timeout_seconds')`, `$failOnTimeout = true`, `$deleteWhenMissingModels = true`.
  - Catch `ImportAlreadyRunningException` and return.
  - `failed()`: set a timeout `failure_message` when none is set; clear `running_since`.
- `ProjectImporter::start()`: remove the extraction. `run()` already extracts when the folder is missing.
- `docker-compose.yml`, `docker-compose.dev.yml`: `DB_QUEUE_RETRY_AFTER=1860`.
- `Dockerfile`, `Dockerfile.dev`: add `pcntl` to `docker-php-ext-install`.
- `docker/nginx.conf`: `fastcgi_read_timeout 300s;` in the PHP location, with a comment that ties it to `max_execution_time` in `docker/php.ini`.
- `docker/supervisord.conf`, `supervisord.dev.conf`: comments only (the import job sets its own tries and timeout).

## Depends on

01, 02.

## Key decisions already made

- Overview decisions 4, 7, 8, 9. Worker command unchanged.
- `retry_after` (1860) must stay above `timeout_seconds` (1800). Say so in the config comment.

Docs: `expanded/architecture.md` → *Queue*, *Request path*, *nginx*.

## Tests

- Job properties: tries 1, failOnTimeout, timeout from config, deleteWhenMissingModels.
- Job on a running import returns without changing the row.
- `failed()` sets the message, clears the claim, and the row stays resumable.
- After `start()` no extracted folder exists; a later `run()` extracts and completes.
- Manual: rebuild the dev image; `php -m | grep pcntl` in the container. Note the result in the task report.
