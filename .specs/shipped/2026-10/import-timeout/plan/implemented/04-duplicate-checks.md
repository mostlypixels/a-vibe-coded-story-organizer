---
title: "Task 04 — Duplicate checks"
---

# Task 04 — Duplicate checks

## Scope

- Migration on `imports`: `archive_sha256` (`char(64)`, nullable); index `(user_id, archive_sha256)`.
- `ProjectImporter::start()`: after validation, hash the upload, then check duplicates before storing.
- `ProjectImporter::findDuplicate(User $user, string $hash): ?Import`.
- `ImportValidationException::alreadyImporting()`.
- Completed duplicate with a project: create the `Pending` row, do not run or queue. `start()` tells the controller (for example `Import::duplicateOf(): ?Import`). The controller redirects to the import page with a warning flash.
- "Import anyway" uses the existing `resume` route; no new route.

Defers the row label and button text to 06.

## Depends on

02.

## Key decisions already made

- Overview decisions 2, 3. Per user only.
- Non-terminal match (queued, running or stalled) → refused, no row, no file.
- Completed match whose `project_id` is null → normal import.

Docs: `expanded/architecture.md` → *Request path*.

## Tests

- Same bytes + non-terminal import → `archive` error, no new row, no stored file.
- Same bytes + completed import with project → Pending row, no dispatch, no run, warning flash.
- Same bytes + completed import, project deleted → normal import.
- Same bytes, other user → normal import.
- Resume on the duplicate row imports it.
- Both run modes (config on and off).
