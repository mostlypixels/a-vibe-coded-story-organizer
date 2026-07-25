# Data model — Revision History Rework

Nothing about the existing shape changes: `revisions` stays one immutable row per
(entity, field, point in time), written only by `App\Services\RevisionRecorder`, with no
`updated_at` and a cascading real `project_id` FK. This document covers the **three new
columns**, who writes them, and the consequences.

## New columns on `revisions`

Migration: `database/migrations/<ts>_add_save_grouping_to_revisions_table.php`.

| Column | Type | Null | Purpose |
|--------|------|------|---------|
| `save_id` | `char(26)` (ULID) | nullable in DDL, always populated by the app | Correlation id: every row one save request wrote for one entity |
| `summary_html` | `text` | yes | Pre-rendered, already-escaped excerpt of this row's change vs. its predecessor |
| `change_count` | `unsignedInteger` | yes | How many changed hunks the full diff has, so the list can say "and X more changes" |

Indexes:

* `['revisionable_type', 'revisionable_id', 'save_id']` — the history page groups by
  `save_id` inside one entity.
* `save_id` alone — the whole-save revert loads a group by id.

The existing `revisions_entity_field_idx` (`revisionable_type, revisionable_id, field,
created_at`) is untouched and still serves the field-filtered view and compare.

> [!NOTE]
> `char(26)` because a ULID is fixed-width; `Str::ulid()` is what Laravel gives us and it
> sorts lexicographically by creation time, which is a free tiebreaker. Portable across
> sqlite/mysql/mariadb/pgsql/sqlsrv — see `.specs/draft/multiple-database-engines`.

### Legacy rows are deleted, not backfilled

The handoff (`§3.1`) proposed leaving pre-existing rows null and "permanently
ungroupable". A null grouping key poisons every read path: `GROUP BY save_id` collapses
*all* legacy rows into one bogus group, and the portable workaround
(`COALESCE(save_id, 'row:' || id)`) is not portable — `||` vs `CONCAT()` differ per engine.

**Decision (owner's call, 2026-07-25): the migration deletes every pre-existing revision
row** — `DB::table('revisions')->delete()`, before adding the columns. Simpler than a
chunked ULID backfill, and it leaves a table where every row was written by the new code
path, so there is no era of rows with no summary and no real grouping.

What that costs, and why it is acceptable here:

* Existing revision history is discarded on upgrade, and `down()` cannot restore it. The
  project is **pre-V1**: the only data that exists is the Melusine demo/test seed, so this
  is a non-event today. Worth one `Removed` line in `CHANGELOG.md`, not a migration
  ceremony. (Revisit if this is ever proposed again after V1, when real installs exist.)
* Nothing else breaks. `revisions` has no inbound foreign keys, and
  `RevisionRecorder::ensureBaseline()` re-seeds a `baseline` row from the entity's live
  value (stamped `updated_at`) the first time each field is written again — so history
  restarts from a truthful starting point rather than from nothing.
* The seeders are unaffected: `DatabaseSeeder` and the Melusine seeders write models
  directly and never create revisions. `database/factories/RevisionFactory.php` (tests
  only) gains a `save_id` default so factory-made rows look like real ones.

### Why the column still stays nullable in DDL

A clean table makes `NOT NULL` tempting, but SQLite cannot `ADD COLUMN … NOT NULL` without
a default (empty table or not), and the alternative — dropping and recreating `revisions`
in this migration — would duplicate the whole table definition across two migration files,
where a reader has to know which one wins. Neither is worth it. So:

* the column is `nullable()` in DDL and **null-free in fact** (legacy rows are gone, and
  `RevisionRecorder` is the only writer);
* a feature test asserts every row written through every entry point carries a `save_id`.

`down()` drops the three columns. It does **not** restore the deleted rows.

## Who writes `save_id`

**One save id per (request, entity).** Not per request: a project *import*
(`App\Services\Import\ProjectGraphImporter`) writes revisions for hundreds of entities in
one request, and grouping them all into one "save" would make *Undo this save* offer to
revert an entire imported project. Not per row either — that's the bug we're fixing.

`RevisionRecorder` gains a memo keyed by `revisionable_type:revisionable_id`:

```php
private array $saveIds = [];

public function currentSaveId(Model $entity): string
{
    $key = $entity::class.':'.$entity->getKey();

    return $this->saveIds[$key] ??= (string) Str::ulid();
}
```

For the memo to survive across the several `record()` calls one request makes, the
recorder must be **one instance per request**: register it in
`app/Providers/AppServiceProvider.php` as `$this->app->scoped(RevisionRecorder::class)`.
Today each `app(RevisionRecorder::class)` / method-injection resolves a fresh instance,
which would hand every field its own save id — this binding is load-bearing, and gets its
own test (two fields saved by one form submit share a `save_id`).

Callers that legitimately want a *new* group inside the same request — the whole-save
revert writing its result — call `$recorder->startNewSave($entity)` to drop the memo entry
first.

### Coalescing keeps the original `save_id`

`RevisionRecorder::record()` overwrites a still-open `origin: automatic` row in place
within `config('revisions.windows')`. That row keeps its original `save_id`, exactly as it
already keeps its original `created_at`: it is the same continuing editing burst, and
rewriting either would make the row claim to be something it is not.

Accepted consequence, to be documented in `documentation/architecture.md`: if one save
touches three fields and one of them coalesces into a still-open row from an earlier
burst, that field's new value lands in the **earlier** group. The group the writer sees
for "the save I just made" then lists two fields, not three. This only ever happens
between consecutive autosaves (manual, revert, import and baseline rows never coalesce),
and the row genuinely does hold the newest value either way, so no content is lost or
misattributed — only the grouping is coarser than the writer's mental model. The
alternative (moving the row into the new group) would silently rewrite an existing group's
membership after the fact, which is worse.

The coalescing `UPDATE` **must recompute `summary_html`/`change_count`**, since the row's
value changed.

### Baseline rows

`ensureBaseline()` seeds a pre-edit `origin: baseline` row. It gets its own fresh save id
(one group of one) and a **null** `summary_html`/`change_count` — it has no predecessor,
so there is nothing to diff against. The UI renders it as "Initial value" (it already has
a dedicated row treatment in `revisions/index.blade.php`).

### Import

`ProjectGraphImporter::importRevisions()` replays a `revisions/<field>.json` sidecar.
Source `save_id`s must not be inserted verbatim (they name groups on another install and
could collide), but the grouping is worth preserving: keep a per-import map
`source save_id => fresh local ULID`, scoped to the import run, and remap. A sidecar row
with no `save_id` (an archive exported before this feature) gets a fresh unique one.

`summary_html`/`change_count` are **not** read from the archive — they are recomputed
during replay. Sidecars are written oldest-first
(`StaticSiteExporter::addRevisions()` orders by `created_at, id`) and the entity is newly
created, so each replayed row's predecessor already exists when its summary is computed.

`StaticSiteExporter::addRevisions()` adds `save_id` to each exported row (next to `id`,
`origin`, `label`, `user_id`, `created_at`) and keeps omitting `summary_html` —
derived data does not belong in an interchange format.

## Who writes `summary_html` / `change_count`

`App\Services\RevisionSummarizer`, called by `RevisionRecorder` on every insert and on
every coalescing update. It diffs the row's value against **its predecessor's value** —
the newest row for the same `(revisionable_type, revisionable_id, field)` strictly older
than the row being written — and asks the differ for:

* `summary_html`: the first changed hunk plus a little context, escaped by the renderer,
  containing only `<ins>`/`<del>` and text, truncated by **rendered length**
  (`config('revisions.summary.max_length')`, default 200 characters) — never by hunk
  count, so a find-and-replace across forty places does not produce a forty-hunk row;
* `change_count`: the total number of changed hunks, so the row can print
  "and *N−1* more changes" and link to compare.

This is the one place the "compute at write, never at read" rule from
`revision-compare-decisions.md` is enforced. It costs one extra `value` read per write
(the predecessor) — acceptable, and the write path already touches that row's neighbourhood.

> [!WARNING]
> **Stale summaries after a prune.** `Revision::prunable()` deletes old `automatic` rows.
> The row that *followed* a deleted row keeps a summary computed against a predecessor
> that no longer exists, so it under-reports (it describes a smaller change than the one
> now visible between the surviving neighbours). This is accepted: recomputing summaries
> during a mass prune would turn a cheap `DELETE` into an O(n) diff job. The compare
> screen always computes live, so the detail view is never wrong — only the list excerpt
> can be. Document it in `documentation/architecture.md`.

## Model changes

`App\Models\Revision`:

* `$fillable` += `save_id`, `summary_html`, `change_count`.
* No cast needed (`save_id` is a plain string; `change_count` is an int column).
* New scope-free helper `hasSummary(): bool` for the view, or handle it in the view — see
  `ui.md`.

`App\Models\Concerns\HasRevisions`: unchanged. The grouping query lives in the new
`RevisionHistory` service (`architecture.md`), not in a model scope — consistent with
CLAUDE.md's "index-page filtering/sorting stays in the controller/service, not in Eloquent
scopes".

## Invariants this feature must not break

1. **Immutability.** The only permitted `UPDATE` remains the coalescing overwrite of a
   still-open `automatic` row, now including its summary columns.
2. **Append-only.** Revert writes new rows. Whole-save revert writes *n* new rows in one
   transaction, all sharing one new `save_id`.
3. **List queries never hydrate `value`.** The history page selects
   `id, save_id, field, created_at, user_id, label, origin, size_bytes, summary_html,
   change_count` — never `value`. `summary_html` exists precisely so it doesn't have to.
4. **`project_id` is always set explicitly** via `revisionProject()`.
5. **Portable DDL and portable queries** — no window functions, no engine-specific string
   concatenation, no `LENGTH()` variants.
