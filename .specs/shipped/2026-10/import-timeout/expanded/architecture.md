# Architecture

## Data model — `imports` table

New migration. Destructive changes are fine before V1.

| Column | Type | Why |
|---|---|---|
| `archive_sha256` | `char(64)`, nullable | Duplicate check. Null for old rows. |
| `running_since` | timestamp, nullable | The run claim (see *Single run*). |
| `queued` | **dropped** → `queued_at` timestamp, nullable | "Queued, not started" needs a time, so a lost job can be seen. |

- Index `(user_id, archive_sha256)`.
- `Import::isRunning()`: `running_since` is newer than `now() - config('import.timeout_seconds')`. An older value is a crashed run and does not block.
- `Import::isQueued()`: `queued_at` is set, the import is not running, and the phase is not terminal.

## Run mode

- Decision: runtime config decides, not the database. See `open-questions.md` #1.
- Both compose files set `IMPORT_RUN_IN_BACKGROUND=true`. Native `.env` stays `false`.
- `config/import.php`: add `timeout_seconds` (env `IMPORT_TIMEOUT_SECONDS`, default `1800`; Les Misérables takes 214 s in Docker).

## Request path — `ImportController::store()` → `ProjectImporter::start()`

1. Validate (`ArchiveValidator`), as today.
2. `hash_file('sha256', ...)` on the upload.
3. Duplicate check, new `ProjectImporter::findDuplicate(User, string $hash): ?Import`:
   - non-terminal match → `ImportValidationException::alreadyImporting()` → field error on `archive`. No row and no file are written.
   - completed match with `project_id` not null → create the row at `Pending`, **do not run or queue it**, and return. The controller redirects to the import page with a warning (see `ui.md`).
4. Store the ZIP and create the row with `archive_sha256`.
5. **Remove the extraction from `start()`.** `run()` already extracts when the folder is missing. The 88 s unpack then runs in the job.

Inline mode: same total time as today. `fastcgi_read_timeout` is the backstop.

## Single run — `ProjectImporter::run()`

- Claim first, with one atomic conditional update:
  `UPDATE imports SET running_since = now WHERE id = ? AND (running_since IS NULL OR running_since < now - timeout)`.
  - 0 rows → throw `ImportAlreadyRunningException`. The job catches it and ends quietly. `resume()` flashes "This import is already running."
- Release (`running_since = null`) in `finally`.
- Clear `queued_at` when the claim succeeds.
- A DB claim instead of `Cache::lock()`: the page needs to read "is it running", and a cache lock cannot be read without being taken.

## Queue — `ProjectImportJob`

- `public $tries = 1;` overrides the worker's `--tries=3`.
- `public function timeout(): int` returns `config('import.timeout_seconds')`.
- `public $failOnTimeout = true;`
- `failed(Throwable)`: if `failure_message` is null, set a timeout message, and clear `running_since`.
- `retry_after` > job timeout: compose sets `DB_QUEUE_RETRY_AFTER=1860`. It applies to all jobs on the `database` connection. That is harmless: it only sets when a reserved job counts as lost.
- `supervisord*.conf`: no change to the command. Update the comment: the import job sets its own tries and timeout.
- Both Dockerfiles: add `pcntl` to `docker-php-ext-install`. Without it, the timeout is not enforced.

## Status endpoint

- `GET /admin/data/imports/{import}/status` → `admin.data.imports.status`, `ImportController::status()`.
- `ImportPolicy::view()`: owner only, same check as `resume`.
- JSON: `phase`, `label`, `state` (`queued|running|stalled|completed`), `failure_message`, `project_url` (null until completed).

## nginx

- `docker/nginx.conf`: `fastcgi_read_timeout 300s;` in the PHP location. Keep it equal to `max_execution_time` in `docker/php.ini`. Add a comment that names the link between them.

## Docs to update

- `documentation/export-import/archive-format.md` (or the import guide it links): run modes, the single-run claim, duplicate rules.
- `documentation/development/docker.md`: the queue worker runs imports; `pcntl`.
- `CHANGELOG.md`.
