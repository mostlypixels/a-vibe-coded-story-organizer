# Task 2 — Widen the 14 registered columns to `longText()`

## Scope

One migration that changes all 14 live columns this feature will autosave — across
`projects`, `acts`, `chapters`, `plotlines`, `events`, `scenes`, `codex_entries` — from
`text()` to `longText()`. Purely a column-type change; no data migration, no new
columns.

Does **not** include any other schema change, and does not touch `revisions.value`
(already created `longText()` directly by task 1).

## Depends on

Nothing (independent of task 1 — can run in parallel/either order, but numbered here for
plan-reading order since it's a prerequisite for task 6's real-payload tests).

## Key decisions already made

* **The 14 columns**, exactly (per `handoff.md` §7 / `expanded/architecture.md`'s
  registry table):
  `projects.description`, `projects.dedication`, `projects.acknowledgements`,
  `projects.preface`, `projects.postface`, `projects.rights`, `acts.description`,
  `chapters.description`, `plotlines.description`, `events.description`,
  `scenes.description`, `scenes.notes`, `scenes.contents`, `codex_entries.description`.
* **No `doctrine/dbal` dependency.** Confirmed absent from this project and not
  required — Laravel 13's schema builder performs `$table->longText('x')->change()`
  natively. If the migration errors demanding `doctrine/dbal`, that is new information
  (Laravel version behavior differing from what this plan assumed) — report it rather
  than silently adding the package.
* This is a real behavioral change **only on MySQL/MariaDB** (65,535-byte `text()` cap
  vs. effectively unlimited `longText()`); pgsql/sqlite/sqlsrv already map both
  identically, so the migration is a no-op there but must still run cleanly on all five
  engines per `multiple-database-engines`.

## Consult

* `expanded/data-model.md` — "Widen the long-text columns" section.
* `handoff.md` §9.8 / §11.1 — the 65,535-byte finding that motivates this task.

## Tests

* After migrating, inspect each of the 14 columns' reported type (via
  `Schema::getColumnType()` or a raw `information_schema`/`PRAGMA` query appropriate to
  the test DB) and assert it is no longer the original `text` type.
* Round-trip a string longer than 65,535 bytes through one of the columns (e.g.
  `Scene::factory()->create(['contents' => str_repeat('a', 100_000)])`) and assert it
  reads back whole, unmodified — this is the regression the migration exists to fix,
  and per CLAUDE.md a bug fix ships with a test that fails before the fix.
