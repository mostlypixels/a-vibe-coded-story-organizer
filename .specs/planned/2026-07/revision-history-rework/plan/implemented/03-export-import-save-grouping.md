# Task 3 — Export / import carry save grouping

## Scope

**Export** — `App\Services\StaticSiteExporter::addRevisions()`: add `save_id` to each row
of the `data/.../revisions/<field>.json` sidecar, beside `id`/`origin`/`label`/`user_id`/
`created_at`. `summary_html` and `change_count` are **not** exported (derived data does
not belong in an interchange format).

**Import** — `App\Services\Import\ProjectGraphImporter::importRevisions()`: keep a
per-import map `source save_id => fresh local ULID`, and stamp the mapped id on each
replayed row. A sidecar row with no `save_id` (an archive exported before this feature)
gets its own fresh unique id. The map is scoped to the import run, so two source groups
stay two groups and no source id is ever inserted verbatim.

Everything else about the import contract is unchanged: `created_at` verbatim, `user_id`
remapped to the importing user, `origin` forced to `RevisionOrigin::Import`, values
re-checked through `ContentSanitizer`.

## Depends on

Tasks 1, 2.

## Key decisions already made

* **Never insert a foreign `save_id` verbatim** — it names a group on another install and
  could collide with a local one.
* **Preserve grouping, not identity** — remapping keeps "these three rows were one save"
  true after import, which is the only property the UI reads.
* The map lives in the importer instance for the duration of one import, not in a column.

## Consult

* `expanded/data-model.md` — *Import*.
* `app/Services/StaticSiteExporter.php` (`addRevisions()`) and
  `app/Services/Import/ProjectGraphImporter.php` (`importRevisions()`).

## Tests

Extend the existing export/import suites (`tests/Unit/Import/*`, and the export test
covering the `include_revisions` toggle):

* exported sidecar rows carry `save_id`;
* round-trip: two source rows sharing a `save_id` share one (different) `save_id` after
  import; two source groups remain two distinct groups;
* a sidecar without `save_id` imports with one fresh unique id per row;
* no imported row has a null `save_id`;
* an import of two entities does not merge their groups.
