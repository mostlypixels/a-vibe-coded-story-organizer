# 09 — Docs & CHANGELOG sync

## Scope

Bring the written record in line with the invariants tasks 01–08 changed. No code.

- **CLAUDE.md** (Codex + bookend sections):
  - Bookend events: "editable but not deletable" → datetime now **frozen**
    (`prohibited` on `is_fixed` in `UpdateEventRequest`); title/description/plotlines
    still editable. The step-function anchor can be neither orphaned nor re-ordered.
  - Start/End resolution now lives in `Project::startEvent()/endEvent()` — the single
    definition (canonical `(event_datetime, id)` order).
  - `upsertAt` enforces the Start baseline itself (gap-free invariant holds on every
    write path, not just entry create).
  - Codex routing: the `->whereIn('type', …)` wording → enum-derived grouped constraint
    via `CodexEntryType::routeKeys()`.
  - Media cleanup: entry **and** project **and** user deletion purge files; disk I/O is
    post-commit in the entry save flow.
- **`documentation/`** (`architecture.md`, and wherever task 09 of the original codex plan
  documented the attribute timeline / media lifecycle): mirror the same four changes with
  the *why* (junior-developer audience, `> [!WARNING]` for the cascade-bypasses-hooks
  pitfall and the post-commit trade-off). Update `glossary.md` only if it defines the
  bookend contract.
- **CHANGELOG.md** `[Unreleased]`:
  - `Fixed`: gap-free invariant on period store; media file leak on project/account
    deletion; disk I/O inside the DB transaction; invisible timeline-editor validation
    errors; unsavable empty attribute values; upload errors hidden past the first file;
    tag query in the entry form partial.
  - `Changed`: `is_fixed` event datetimes frozen; baseline-removal guard now 403; codex
    routes/nav derived from `CodexEntryType`; responsive nav highlights the current codex
    type (the Q4 behavior change); index tag filter hides tags with no entries;
    `applies_to` narrowing hint on the attribute form.
- Final gate: full `composer test` green, `vendor/bin/pint` clean across the whole diff.

## Depends on

01–08 all in `plan/implemented/`.

## Key decisions already made

All of `00-overview.md`'s binding decisions — this task records them; it must not reopen
any.

## Consult

Every `.specs/refactor_codex/expanded/*.md` doc (each ends with the doc-impact notes this task
executes), `.claude/guidelines.md` §§ Documentation/Changelog.

## Tests

None new — run the full suite as the verification gate.
