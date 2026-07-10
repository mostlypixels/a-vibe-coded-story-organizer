# Task 05 — Register the SessionStart hook in .claude/settings.json

## Scope

Wire the task-04 script into Claude Code so it actually fires at session start, for every
checkout (tracked settings, since the hook is portable and fail-open).

**Builds:**
- A `SessionStart` hook entry in **tracked** `.claude/settings.json` running
  `php .claude/hooks/session-start.php`.

**Does NOT build:** the script (task 04) or the logic (task 03).

## Depends on

- Task 04 (the script must exist and be verified before it is registered).

## What to do

Use the **`update-config`** skill to add the hook — settings.json edits and hook wiring are
exactly its job. The entry must:
- Fire on `SessionStart`.
- Run `php .claude/hooks/session-start.php` (relative to repo root; portable across OSes).
- Live in **tracked** `.claude/settings.json`, not `.claude/settings.local.json` — the hook is
  safe for teammates because it is portable and fail-open (binding decisions 8, 9).

Confirm the command form works on the reference OS and would degrade fail-open elsewhere (if `php`
is not on PATH, the hook errors and the session still starts — verify this rather than assume it).

## Key decisions already made

- Tracked settings, portable PHP hook, fail-open (binding decisions 8, 9).
- Use `update-config` for settings edits (project convention).

## Docs to consult

- `expanded/architecture.md` (Wiring — hook registration)
- `expanded/testing.md` (confirm the hook fires; confirm fail-open when php is absent)

## Tests this task adds

None automated (settings wiring is environment config). Verify by starting a fresh session and
confirming the hook runs and the cache is read/created. Record the result in `resolution-log.md`.
Update `CHANGELOG.md` under `Added`.
