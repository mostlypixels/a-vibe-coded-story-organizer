---
title: Import Timeout — Plan Overview
---

# Plan Overview

Manual. Never itself implemented or moved to `plan/implemented/`.

## Execution order

| # | Task | Purpose |
|---|------|---------|
| 01 | `01-run-mode-from-config.md` | Run mode from config only. Drop the column and checkbox. Docker turns it on. |
| 02 | `02-run-claim.md` | `running_since` claim in `run()`, `queued_at` in place of `queued`. One run per import. |
| 03 | `03-queue-and-docker.md` | Job tries and timeout, `failed()`, `pcntl`, `retry_after`, nginx timeout, unzip out of `start()`. Depends on 01, 02. |
| 04 | `04-duplicate-checks.md` | Archive hash. Block a running/stalled duplicate, warn on a completed one. Depends on 02. |
| 05 | `05-status-endpoint.md` | `admin.data.imports.status` JSON + `ImportPolicy::view`. Depends on 02. |
| 06 | `06-import-page.md` | Row states, 3 s polling, hidden actions, "Import anyway". Depends on 01, 04, 05. |
| 07 | `07-docs-changelog.md` | Docs, CHANGELOG, manual Docker check. Depends on 01 to 06. |

## Binding design decisions (do not re-litigate)

Resolved in the grill; recorded in `../resolution-log.md`.

1. **Run mode is config only** (`import.run_in_background`, env `IMPORT_RUN_IN_BACKGROUND`). No DB column, no checkbox.
2. **Duplicate warning lasts while the earlier project exists** (`project_id` not null). No time window.
3. **Duplicate checks run in both runtimes**, in `start()`, before the run mode is read.
4. **Worker command does not change.** The job sets `tries = 1` and its own timeout. `retry_after` goes to 1860 s for the `database` connection.
5. **Poll every 3 s**, only while a row is queued or running.
6. **On failure, reload** to the stalled row (phase, `failure_message`, Resume, Discard).
7. **Validation and hashing stay in the request.** Measured in Docker with Les Misérables: 0.8 s validate, 0.16 s hash.
8. **Discard is hidden while running.** It stays for a queued row; the job sets `$deleteWhenMissingModels = true`.
9. **`pcntl` in both Dockerfiles.** Native Windows PHP has none and does not need it (inline runs).

## Core invariants every task preserves

- **One run per import.** Every path into `ProjectImporter::run()` (job, inline store, resume) takes the claim first.
- **A failed run stays resumable.** `phase`/`id_maps` checkpoints are unchanged; the queue never retries.
- **Owner-only access** to an `Import` row through `ImportPolicy`. New endpoint → owner test + 403 test.
- **No row or file is written for a refused upload** (validation error or running duplicate).
