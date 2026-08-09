# 03 — The recorder

## Scope

- `App\Services\WordCountSnapshotRecorder::record(Project $project): void` — sum, then
  `upsert`.
- Model-event wiring in `booted()`:

  | Event | Guard |
  | --- | --- |
  | `Scene::saved` | `wasChanged('word_count')` |
  | `Scene::deleted` | — |
  | `Chapter::deleted` | — |
  | `Act::deleted` | — |

**Not** in this task: reading the rows (04), the import path (11), the demo generator (12).

## Depends on

01 (`WriterDay`), 02 (the table).

## Key decisions

- **`upsert`, not `updateOrCreate`.** One atomic statement; two autosaves in the same
  millisecond must not race into a unique-key violation.
- **`saved`, not `saving`** — the recorder sums the table and needs the row written. This is
  the opposite of the `word_count` hook next to it in `Scene::booted()`, and the docblock
  should say why so nobody "fixes" it.
- **Hooks, not controllers.** `RevisionReverter` writes through `$entity->save()` and never
  touches `FieldAutosaveController`; a controller-level call goes stale the moment someone
  uses Undo. Same reasoning as `documentation/word-count.md`.
- `Chapter::deleted` and `Act::deleted` exist because their scenes cascade at the **database**
  level, firing no `Scene::deleted`. Both controllers call `$model->delete()` inside a
  `DB::transaction`, so the hooks fire and the upsert joins that transaction.
- The date comes from the **project owner's** zone, not the actor's.
- Do not optimise the `SUM` into a denormalised column — benchmarked and rejected in
  `documentation/word-count.md`. Autosave debounces at 2 s; the query is sub-millisecond.

## Consult

`expanded/architecture.md` → *The write path* · `documentation/word-count.md` → *The invariant*

## Tests

Everything in `expanded/testing.md` → *`WordCountSnapshotTest`* and *`WriterDayTest`*, in
particular:

- One row per day however many times you save.
- Deleting an act **with its children reassigned** leaves the row unchanged — no words lost.
- Reverting a revision updates the row.
- A `DB::table()` bulk write records nothing.
- Two saves either side of the owner's local midnight produce two rows.
