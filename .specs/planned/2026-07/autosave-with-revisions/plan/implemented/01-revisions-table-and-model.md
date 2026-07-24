# Task 1 — `revisions` table + `Revision` model + `RevisionOrigin` enum + config

## Scope

Build the storage core: the `revisions` migration, `App\Models\Revision` (with
`MassPrunable` and its `prunable()` query), `App\Enums\RevisionOrigin`, and
`config/revisions.php` (retention default + per-field coalescing windows + character
caps — the data other tasks will read, not yet wired to anything that writes).

Does **not** include: the `RevisionSetting` singleton (task 12 — this task's
`prunable()` may reference `config('revisions.retention_days')` directly for now, since
`RevisionSetting::current()` doesn't exist yet; task 12 swaps that read to the
singleton). Does not include `HasRevisions`/`AutosavableFields` (task 3), any actual
write path (task 4), or widening the 14 live columns (task 2 — `revisions.value` itself
is created `longText()` directly in this task's migration, since it's a new column with
no legacy data to widen).

## Depends on

Nothing (first task).

## Key decisions already made

* Schema, indexes, and column types: see `data-model.md`'s `revisions` table block —
  copy it, don't redesign it. `project_id` is a real `foreignId()->constrained()->
  cascadeOnDelete()`, not polymorphic (deleting a Project cascades to its tree at the DB
  level, bypassing Eloquent events — a `deleting` hook would silently never fire).
* `origin` is a string column cast to the `RevisionOrigin` enum (`automatic | manual |
  revert | import | baseline`) — matches the existing `SceneStatus`-style string-backed
  enum convention in `app/Enums`.
* `size_bytes` is `unsignedInteger`, set from `strlen($value)` — this task defines the
  column; task 4 is the one that actually populates it on every write.
* No `updated_at` on `revisions` — a closed revision is immutable; `created_at` only,
  set explicitly (never `useCurrent()` implicit default in application code — the
  migration can default it for direct-insert safety, but every `RevisionRecorder` write
  sets it explicitly per `data-model.md`).
* `Revision::prunable()` — the **single most safety-critical query in this feature**:
  `origin = automatic AND label IS NULL AND created_at < now()->subDays(retention)`,
  **excluding** the newest revision per `(revisionable_type, revisionable_id, field)`
  via `whereNotIn('id', subquery selecting MAX(id) grouped by those three columns)`.
  This must be portable (no window function) — see `data-model.md`'s exact code block.
* `config/revisions.php` shape: `retention_days` (default 90), `windows` (keyed
  `Model.field`, `Scene.contents` => 60, `default` => 300), `caps` (keyed `Model.field`,
  `Scene.contents` => 1_000_000, `Project.rights` => 1_000, `default` => 100_000).
  Mirrors `config/import.php`'s shape (read it for the pattern).

## Consult

* `expanded/data-model.md` — the `revisions` table DDL, `Revision` model, enum, and
  config file, verbatim starting points.
* `expanded/overview.md` — acceptance criteria mentioning pruning safety.
* `handoff.md` §1 (storage), §4.2 (retention safety rules), §9.9 (`size_bytes`
  rationale).

## Tests

* Migration runs and rolls back cleanly (standard `RefreshDatabase` smoke coverage via
  any factory-backed test in this task).
* `RevisionOrigin` enum has exactly the five expected cases.
* `Revision::prunable()`, tested directly against seeded rows (no controller needed
  yet — construct `Revision` rows via factory):
  * An `automatic`, unlabeled, old row with a *newer* sibling revision on the same
    `(type, id, field)` → included (prunable).
  * The same row when it is the **only/newest** revision for that field → excluded, even
    though it's `automatic`, unlabeled, and old.
  * A `manual`/`revert`/`import`/`baseline` row, old and unlabeled → excluded regardless
    of age.
  * A labeled `automatic` row, old → excluded.
  * An `automatic`, unlabeled row **within** the retention window → excluded (not yet
    old enough).
* `config('revisions.windows')`/`caps` return the documented defaults.
