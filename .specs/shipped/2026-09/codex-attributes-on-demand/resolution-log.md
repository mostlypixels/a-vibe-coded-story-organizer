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
- Create-form baseline errors render as one list under the picker, not beside each input.
  The inputs are Alpine-generated inside `<template x-for>`, so a per-input
  `x-input-error` cannot be placed there. Errors here are only reachable by a hand-made
  post anyway — the picker cannot pick a foreign or wrong-type attribute.
- `ProjectGraphImporter` is left alone. An archive exported before this change restores blank
  rows and re-attaches everything, but pre-V1 archives are throwaway.

## Deviations from the spec/plan

- Task 01 kept `forEntry()` public instead of making it private. `00-overview.md`'s task-01
  row and the task file both say it goes private now, but `edit()` in
  `CodexEntryController` still calls it directly until task 05 switches the edit form onto
  `attached()` — `architecture.md` confirms "`forEntry()` stays as-is" for this task's scope.
  Making it private now would break every edit-page test. Visibility change moves to task 05,
  once it has no external caller left.

## Issues → resolutions

- Task 04's test list asks for "attach of another project's attribute → 404". Reachable
  behavior is a validation error instead: `AttachCodexAttributeRequest` validates
  `codex_attribute_id` with a project-scoped `Rule::exists`, so a foreign id fails
  validation before the controller's `abort_unless` project_id guard ever runs. The guard
  stays as defense-in-depth per the task's key decisions; the test asserts
  `assertSessionHasErrors('codex_attribute_id')` instead of a 404 status.

- `CodexAttributeSheetsTest::test_for_entry_includes_an_attribute_the_entry_has_no_value_for`
  was deleted, not rewritten. `forEntry()` is private from task 05 on, so the test could no
  longer call it, and its subject — an attribute with no row — is already covered from
  outside by the `attached()` and `unattachedFor()` tests.
