# 02 — The snapshots table

## Scope

- Migration creating `word_count_snapshots`:

  ```
  id
  project_id    foreignId, constrained, cascadeOnDelete
  recorded_on   date
  word_count    unsignedInteger default 0
  timestamps
  unique(project_id, recorded_on)
  ```

- `App\Models\WordCountSnapshot` — `belongsTo(Project)`, `recorded_on` cast to `date`.
- `Project::wordCountSnapshots(): HasMany`.
- A factory.

**Not** in this task: writing rows (03), reading them (04). Nothing calls this yet.

## Depends on

Nothing. (01 and 02 are independent; 03 needs both.)

## Key decisions

- **No backfill in the migration.** There is no release, so nothing pre-exists at v1, and the
  dev database is reseeded freely. The migration creates the table and stops.
- `recorded_on` is a **`date`**, not a `datetime` — the row means "the total at the end of
  this writer-day", and a timestamp invites re-deriving the day in the wrong zone.
- The **unique index is also the range index**. A month query filters `project_id` then
  `recorded_on`, which the composite serves left-to-right. Do not add a second index.
- `unsignedInteger default 0`, never nullable — 0 is a real answer.
- No `is_baseline` column and no `user_id`.

## Consult

`expanded/data-model.md` → *New table*

## Tests

- A migration test in the style of `AddWordCountToScenesMigrationTest`: the table, the
  columns, the unique constraint.
- Deleting a project cascades its snapshots away.
- The unique constraint rejects a second row for the same `(project, date)`.
