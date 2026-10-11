# Testing

Extend `tests/Feature/ImportTest.php`. Use `Queue::fake()` for dispatch, and call the job's `handle()` directly to run it.

## Single run

- `run()` throws `ImportAlreadyRunningException` when `running_since` is fresh. It does not change `phase`.
- `run()` takes over a stale claim (older than `timeout_seconds`).
- `running_since` is cleared after success and after an exception.
- Resume on a running import flashes "already running" and does not dispatch.
- The job has `tries` 1, `failOnTimeout`, and the timeout from config.
- `failed()` sets a message and clears the claim, and the row stays resumable.

## Request path

- `start()` does not extract: no extracted folder after store in background mode.
- Background store dispatches the job, sets `queued_at`, and does not run phases.
- Inline store still imports and redirects to the project. Update the existing tests that expect `queued`.

## Duplicates

- Same bytes + non-terminal import → `archive` error, no new row, no stored file.
- Same bytes + completed import with a project → Pending row, not run or queued, warning shown.
- Same bytes + completed import whose project was deleted → normal import.
- Same bytes from **another user** → normal import.
- "Import anyway" (resume) on the duplicate row imports it.

## Status endpoint

- Owner gets the JSON with each state. `project_url` is set only when completed.
- Non-owner → 403. Guest → redirect to login.

## Run mode

- Background mode follows `config('import.default_run_in_background')` / the new source chosen in open question #1.
- Remove or rewrite `test_resume_follows_the_current_toggle_value_not_the_original` and the settings toggle tests if the column goes.

## Manual (Docker)

- `make up`, then import the Les Misérables archive: no 504, the phases update, and the link appears.
- Double-click Import: one project.
- `docker compose exec app php -m | grep pcntl`.
