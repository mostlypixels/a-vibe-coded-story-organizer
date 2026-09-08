# Codex attributes on demand — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

> [!IMPORTANT]
> An **exception log, not a work journal**. A task that went to plan gets no entry — the
> diff and the task file already record what was built. Bullets under the headings below,
> root cause first, no per-task sections.

## Feedback & decisions

- `forEntry()` becomes private. After this feature it has no production caller, and a public
  method with none is an abstraction the project's rules say not to keep.
- The cleanup migration uses the query builder rather than the row-value SQL in
  `data-model.md`. Row-value `IN` ties the migration to SQLite 3.15+/MySQL, and the engine
  is not settled (see the shelved `multiple-database-engines` spec).
- A posted `attribute_baselines` key always writes a row, blank included. The expanded docs
  had the create form dropping a blank pick while attach on the edit form kept one — the
  same act, two results. Supersedes `testing.md`'s "posting a blank baseline writes no row".
- The detach button is the only detach path. `overview.md` claims removing the last period
  already detaches a pair; it does not, because the timeline partial renders a delete button
  only for non-baseline periods, so a pair always keeps its baseline row. No baseline delete
  button is added — that would be a second, unconfirmed way to destroy the same data.
- `ProjectGraphImporter` is left alone. An archive exported before this change restores blank
  rows and re-attaches everything, but pre-V1 archives are throwaway.

## Deviations from the spec/plan

_None yet._

## Issues → resolutions

_None yet._
