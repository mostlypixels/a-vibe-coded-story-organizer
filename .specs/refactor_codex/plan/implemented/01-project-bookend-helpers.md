# 01 — Project bookend helpers (finding 8)

## Scope

Extract "the project's Start/End event" into single-source-of-truth helpers and remove the
two duplicated queries.

- Add `Project::startEvent(): Event` and `Project::endEvent(): Event` public methods on
  `app/Models/Project.php` — earliest / latest `is_fixed` event ordered by
  `(event_datetime, id)` (resp. `orderByDesc` twice), `firstOrFail()`. Methods, **not**
  `HasOne` relations. Comment why the id tie-break is part of the contract.
- `app/Services/AttributeTimeline.php:193-212`: `startEvent()`/`endEvent()` become
  one-line delegates to `$this->entry->project->startEvent()/endEvent()` (keep the thin
  private methods; the query must exist exactly once).
- `app/Http/Controllers/CodexEntryController.php`: delete the private
  `startEvent(Project $project)` (`:291-298`); `edit()` calls `$project->startEvent()`.

Does **not** touch validation of bookend edits (task 02) or `upsertAt` behavior (task 03).

## Depends on

Nothing — first task.

## Key decisions already made

- Model methods, not relations (ordering + `firstOrFail` semantics; nothing eager-loads them).
- Canonical order `(event_datetime, id)` is binding (00-overview invariants).

## Consult

`.specs/refactor_codex/timeline-integrity.md` (§ Finding 8).

## Tests

In `tests/Feature/ProjectTest.php`:

- `test_start_and_end_event_helpers_resolve_the_bookends`: fresh project →
  `startEvent()` is the `is_fixed` year-0000 event, `endEvent()` the year-3000 one.
- Tie-break determinism: create two fixed events sharing a datetime directly in the test →
  `startEvent()` resolves by lowest id, `endEvent()` by highest.
- Existing `AttributeTimelineTest` suite stays green (regression net for the delegation).
