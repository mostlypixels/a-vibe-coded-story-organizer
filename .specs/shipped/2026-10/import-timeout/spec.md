---
status: shipped
shipped: 2026-10-11
planned: 2026-10-11
expanded: 2026-10-11
---

# Import Timeout

In Docker, a big import shows nginx's 504 page after 60 s, but the import keeps running. A writer who thinks it failed and retries gets a second full project (#289). The Les Misérables archive takes about 3.5 min in Docker (measured 2026-10-11, after #323: 88 s unpack, 126 s import).

Causes:

- `docker/nginx.conf` has no `fastcgi_read_timeout`, so nginx stops at 60 s. `php.ini` allows 300 s.
- Background import is off, even though Docker runs a queue worker.
- `queue:work` uses the default 60 s `--timeout`, so a background import is killed partway through. The database queue's `retry_after` is 90 s and the worker has `--tries=3`, so one import can run twice.
- Nothing stops a second run of the same import, or a second upload of the same file.
- The import page shows no progress.

## Goals

- A big import in Docker finishes without a 504.
- One import runs once: no retry from the queue, no second run from a second click.
- The import page shows the current phase while an import runs, and a link to the project when it is done.
- An archive that you imported recently gives a warning with "Import anyway". The same archive that is still importing is blocked.

## Non-goals

- Faster imports. The slow unpack on the bind mount is a separate problem.
- Native runs: they stay inline (no queue worker).
- Cancelling a running import.
- Duplicate checks on anything other than the archive bytes, for example the same project from a different backup.

## Rough approach

- Docker turns on background import (`IMPORT_RUN_IN_BACKGROUND`). Native stays inline.
- The import job gets a timeout above the longest import and a single try. The queue's `retry_after` goes above the job timeout.
- One lock per import, shared by the job and by an inline resume. Hide Resume while an import runs.
- The import page polls a status endpoint for the phase (`ImportPhase` exists). Authorize it through the existing import policy.
- Store a hash of the archive on the import. Same hash in progress → block. Same hash finished recently → warn, with "Import anyway".
- `fastcgi_read_timeout` in `docker/nginx.conf` matches `php.ini`, as a backstop for inline runs.

## Open questions

- What does "recently" mean for the warning: a time window, or "while that project still exists"?
- Does the warning also apply to native inline imports? Probably yes: the hash check does not depend on the runtime.
- Do the worker settings change for all queued jobs, or only for the import queue?
- Polling interval, and what the page shows when an import fails.
