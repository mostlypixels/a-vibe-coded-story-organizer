# Task 1 — `save_id` / summary columns, on a cleaned table

## Scope

One migration, `database/migrations/<ts>_add_save_grouping_to_revisions_table.php`:

1. **delete every existing revision row** — `DB::table('revisions')->delete()` — before
   touching the schema (owner's decision: legacy history is discarded rather than
   backfilled, see *Key decisions*);
2. add `save_id` `char(26)` **nullable**, `summary_html` `text` nullable,
   `change_count` `unsignedInteger` nullable;
3. add indexes `['revisionable_type', 'revisionable_id', 'save_id']` and `['save_id']`;
4. `down()` drops the indexes and the three columns — it **cannot** restore the deleted
   rows, and its docblock must say so.

`App\Models\Revision`: add the three columns to `$fillable`. No cast is needed
(`save_id` is a plain string, `change_count` an int column).

`database/factories/RevisionFactory.php`: default `save_id` to a fresh
`(string) Str::ulid()` per created row, so factory-made rows look like real ones and no
test has to remember to set it. (The seeders need no change — `DatabaseSeeder` and the
three Melusine seeders write models directly and never create revisions.)

Does **not** stamp `save_id` on new writes (task 2) or populate the summary columns
(task 9) — this task only makes the columns exist on a table with no legacy rows in it.

## Depends on

Nothing.

## Key decisions already made

* **Delete the legacy rows; do not backfill them.** Binding decision 4. A null grouping key
  poisons every read path (`GROUP BY save_id` collapses all legacy rows into one bogus
  group, and the `COALESCE(save_id, 'row:' || id)` workaround is not portable across the
  five supported engines), while a per-row ULID backfill only buys an era of rows that have
  grouping but no summaries. Deleting is simpler and leaves a table where every row came
  from the new write path.
  * The cost — existing history is gone on upgrade — is a non-event: the project is
    **pre-V1** and the only data that exists is the Melusine demo/test seed. One `Removed`
    line in `CHANGELOG.md` (task 19) covers it; no export-first ceremony.
  * Nothing else breaks: `revisions` has no inbound foreign keys, and
    `RevisionRecorder::ensureBaseline()` re-seeds a `baseline` row from the entity's live
    value (stamped `updated_at`) the first time each field is written again.
* **Nullable in DDL even so.** SQLite cannot `ADD COLUMN … NOT NULL` without a default
  (empty table or not), and dropping/recreating `revisions` here would duplicate the whole
  table definition across two migration files, where a reader has to know which one wins.
  The column is null-free in fact, guarded by a test rather than a constraint.
* **ULID, not UUID** — fixed 26 chars, and lexicographically sortable by creation time,
  which gives a free deterministic tiebreaker.
* Portable DDL only: no partial indexes, no generated columns.

## Consult

* `expanded/data-model.md` — *New columns*, *Legacy rows are deleted, not backfilled*,
  *Why the column still stays nullable in DDL*.
* `database/migrations/2026_07_22_000000_create_revisions_table.php` — the column-comment
  style this migration should match.
* `tests/Feature/BackfillBaselineRevisionsMigrationTest.php` — the migration-test pattern.

## Tests

`tests/Feature/AddSaveGroupingMigrationTest.php`:

* rows created before the migration are gone after it (the table is empty);
* the three columns exist, and a row can be written carrying all three (fillable);
* `down()` drops them cleanly and `up()` runs again afterwards;
* after the migration, the first write to a field re-seeds a `baseline` row from the live
  value, so history restarts rather than staying empty;
* `Revision::factory()->create()` produces a row with a non-null, unique `save_id`.
