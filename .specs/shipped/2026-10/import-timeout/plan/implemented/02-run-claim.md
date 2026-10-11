---
title: "Task 02 — Run claim"
---

# Task 02 — Run claim

## Scope

- Migration on `imports`: add `running_since` (timestamp, nullable); replace `queued` (bool) with `queued_at` (timestamp, nullable).
- `config/import.php`: `timeout_seconds` (env `IMPORT_TIMEOUT_SECONDS`, default 1800).
- `Import`: casts; `isRunning()`, `isQueued()`.
- `App\Exceptions\ImportAlreadyRunningException`.
- `ProjectImporter::run()`: claim first, release in `finally`, clear `queued_at` on claim.
- `ImportController`: set `queued_at` when queueing. `resume()` catches the exception and flashes "This import is already running." without dispatching.

Defers job properties to 03 and UI to 06.

## Depends on

Nothing.

## Key decisions already made

- The claim is one conditional `UPDATE` (atomic on SQLite). No `Cache::lock()`: the page must read the running state.
- A claim older than `timeout_seconds` counts as a crash and can be taken over.

```php
public function isRunning(): bool; // running_since > now() - timeout
public function isQueued(): bool;  // queued_at set, not running, phase not terminal
```

Docs: `expanded/architecture.md` → *Data model*, *Single run*.

## Tests

- Fresh claim → `run()` throws, `phase` unchanged.
- Stale claim → `run()` takes it over and completes.
- `running_since` is null after success and after a phase exception.
- `queued_at` is cleared when the run starts.
- Resume on a running import: flash, no dispatch, no phase change.
- `isRunning()` / `isQueued()` per state.
