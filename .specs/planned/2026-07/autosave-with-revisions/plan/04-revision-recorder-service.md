# Task 4 — `RevisionRecorder` service

## Scope

`App\Services\RevisionRecorder`, the one place that writes to `revisions`:

* `record(Model $entity, string $field, string $value, User $user, RevisionOrigin
  $origin, ?string $label = null): Revision` — coalescing-aware insert/update.
* `ensureBaseline(Model $entity, string $field): void` — seeds a `baseline` revision
  from the entity's *current* column value if (and only if) no revision exists yet for
  that `(entity, field)`.
* A way to read "the last recorded value for this field" (used by callers to skip
  writing a byte-identical revision) — e.g. `lastValueFor()`/`lastRevisionFor()`.

Called directly by tests in this task (not yet by any controller — task 6 wires the
controller to call it) and, in task 5, by the backfill migration.

Does **not** include the byte-identical no-op *decision* (whether to call `record()` at
all) — per `expanded/architecture.md`, that comparison happens one level up, in the
caller (task 6's controller), comparing the incoming value against the entity's current
column value before deciding to call `record()`. This service's job is coalescing +
baseline only.

## Depends on

Task 1 (`Revision`, `RevisionOrigin`, config windows), task 3 (`HasRevisions::
revisionProject()`, used to set `project_id`).

## Key decisions already made

* **Coalescing**: for `origin: automatic` only, look for an open revision on the same
  `(revisionable, field)` created within the field's configured window
  (`AutosavableFields::windowSeconds()`); if found, `UPDATE` its `value`/`size_bytes` in
  place; otherwise `INSERT`. Non-automatic origins (`manual`, `revert`, `import`) always
  insert a fresh row, never coalesce — this is what makes a form-submit Save always a
  permanent, individually-visible entry even if it happens seconds after an autosave.
* **`size_bytes = strlen($value)`** set on every insert *and* every coalescing
  overwrite — same write path, one line, per `handoff.md` §9.9.
* **Baseline timestamp is `$entity->updated_at`, not `now()`** — the pre-edit value
  provably held from `updated_at` onward; stamping `now()` would break compare-by-date
  for the entire pre-baseline era (`handoff.md` §9.2). `user_id` on a baseline is the
  *project owner* (`revisionProject()->user_id`), not any particular editor, since no
  one "wrote" the baseline.
* **`ensureBaseline()` is a no-op when the current value is null/empty** — an empty
  field has nothing worth preserving as a baseline.
* **`project_id` is always set explicitly** from `$entity->revisionProject()->id` (or
  `$entity->id` when `$entity` is itself a `Project`) — `revisionProject()` already
  returns the right target for that case per task 3.

## Consult

* `expanded/architecture.md` — the `RevisionRecorder` code sketch (`record()`,
  `ensureBaseline()`).
* `expanded/data-model.md` — "Baseline seeding + backfill" section.
* `handoff.md` §2.2 (coalescing), §2.6 (snapshot-after + baseline), §9.2 (baseline
  timing/timestamp).

## Tests

* `record()` with `origin: automatic` twice within the window on the same field →
  one row, `value` updated to the second call's value, `size_bytes` matches.
* `record()` with `origin: automatic`, second call after `travel()` past the window →
  two rows.
* `record()` with `origin: manual` twice in immediate succession → two rows (manual
  never coalesces), both present in `revisions`.
* `record()` sets `project_id` correctly for a `Scene` (walks `chapter.act.project`) and
  for a `Project` itself (equals its own id).
* `ensureBaseline()` on a field with no existing revisions and a non-empty current
  value → inserts one `baseline` row with `created_at = $entity->updated_at` and
  `user_id = ` the project owner.
* `ensureBaseline()` called twice → only ever one baseline row (idempotent).
* `ensureBaseline()` on an empty/null field → no row inserted.
* Use `travel()` for all time-based assertions, never a real `sleep()` (paratest
  convention).
