# Task 01 — Data model foundation

## Scope

Build the two new database tables and their models/enum/policy — the plumbing every
later task builds on. **Does not** implement any actual import logic; this task's
tests are pure model/policy tests (factories, casts, lazy-create defaults,
authorization), not an end-to-end import.

* Migration `create_import_settings_table`: singleton table (no `project_id`/
  `user_id`), columns `max_archive_kilobytes` (integer, default `204800`) and
  `run_in_background` (boolean, default `false`).
* Migration `create_imports_table`: columns `user_id` (FK, cascade-delete),
  `project_id` (nullable FK, cascade-delete... actually **set null** — see note
  below), `archive_path` (string), `archive_original_name` (string — for the
  in-progress list's display, task 08), `phase` (string, cast to `ImportPhase`),
  `id_maps` (json, nullable), `queued` (boolean, default false), `failure_message`
  (nullable string), timestamps.
* `app/Enums/ImportPhase.php`: string-backed enum `Pending`, `Project`, `Timeline`,
  `Story`, `Codex`, `Completed`, `Failed` (mirror `SceneStatus`'s shape: a `label()`
  method for the UI's status text — task 08 consumes this).
* `app/Models/ImportSetting.php`: `fillable = ['max_archive_kilobytes', 'run_in_background']`,
  cast `run_in_background` to boolean, `current(): self` static accessor (lazy-create
  from `config('import.default_max_archive_kilobytes')` /
  `config('import.default_run_in_background')`, **not** memoised) — copy
  `CrawlerSetting::current()`'s exact shape and doc-comment style.
* `app/Models/Import.php`: `fillable = ['project_id', 'archive_path',
  'archive_original_name', 'phase', 'id_maps', 'queued', 'failure_message']`, casts
  `phase` → `ImportPhase`, `id_maps` → `array`. `belongsTo(User)`, `belongsTo(Project)`
  (nullable — no project yet at `phase = pending`).
* `config/import.php`: `default_max_archive_kilobytes` (env `IMPORT_MAX_ARCHIVE_KILOBYTES`,
  default `204800`), `default_run_in_background` (env `IMPORT_RUN_IN_BACKGROUND`,
  default `false`).
* `app/Policies/ImportPolicy.php`: `resume(User $user, Import $import): bool` and
  `discard(User $user, Import $import): bool`, both `$user->id === $import->user_id`
  — this is the one part of the feature that's a normal owned-resource policy, not
  the `CrawlerSetting`-style any-authenticated-user exception (see `00-overview.md`'s
  binding defaults).

**Note on `project_id`'s delete behavior**: use `nullOnDelete()`, not
`cascadeOnDelete()` — if a `Project` created by an in-progress import is deleted by
some other path before the `Import` row itself is cleaned up, the `Import` row should
survive as an orphaned/failed record the user can still see and discard, not vanish
silently. Task 05 handles setting `failure_message` in that edge case if it comes up;
this task only needs the FK behavior correct.

## Depends on

Nothing — this is the first task.

## Key decisions already made (don't re-decide)

* Two separate tables/models (`ImportSetting`, `Import`) — settings vs. tracking
  record, per `00-overview.md`.
* `ImportPhase` order is `Pending → Project → Timeline → Story → Codex → Completed`,
  with `Failed` as a terminal error state distinct from "stalled mid-phase" (a
  stalled import just sits at its last completed non-terminal phase; `Failed` is
  reserved for `ArchiveValidator`/`ContentSanitizer` rejections that never get a
  chance to reach phase 1 — see task 05 for exactly when each is set).
* `ImportPolicy` — real ownership check, not the any-authenticated-user exception.

## Docs to consult

`data-model.md` → *Checkpointing & resumability* (full column list/rationale for
`imports`); `architecture.md` → *`ImportSetting` — the configurable size cap*.

## Tests

* `ImportSetting::current()` lazy-creates from config defaults on a fresh install
  (no row yet) and returns the existing row on subsequent calls (no duplicate rows).
* `ImportSetting` casts `run_in_background` to a real boolean.
* `Import` factory (`database/factories/ImportFactory.php`) producing a valid row at
  each `ImportPhase`; casts round-trip `id_maps` as an array.
* `ImportPolicy`: owner can `resume`/`discard` their own `Import`; a different user
  cannot (assert `false`, this is a unit-level policy test, not yet an HTTP 403 —
  that's task 06's job).
* Deleting a `Project` referenced by an `Import` row leaves the `Import` row intact
  with `project_id` null (confirms `nullOnDelete()`).
