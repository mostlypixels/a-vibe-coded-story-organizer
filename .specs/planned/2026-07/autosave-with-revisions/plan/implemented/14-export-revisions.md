# Task 14 — Zip export: include revisions

## Scope

* `ExportRequest` gains an `include_revisions` boolean toggle, mirroring the existing
  `include_images` toggle (confirmed this session in `ExportController::store()`:
  `$exporter->export($project, $request->boolean('include_images'))`).
* `App\Services\StaticSiteExporter` (read its `data/manifest.json` emission this
  session — `includes_media` flag, `addJson()` helper) gains an `includes_revisions`
  flag in the manifest and, when the toggle is on, writes one file per field holding
  that field's **whole history as an array** — e.g. `.../scene-N/revisions/
  contents.json` — not one file per revision (a heavily-edited scene must not add
  hundreds of zip entries, per `handoff.md` §8).
* The export UI: an `include_revisions` checkbox on whatever Blade view already renders
  the `include_images` toggle for the export form.

Does **not** include the import side (task 15) or EPUB/PDF export (unaffected — those
never include revisions, unchanged).

## Depends on

Task 1 (`Revision` model to query), task 4 (nothing structurally new needed here beyond
reading existing `revisions` rows).

## Key decisions already made

* **EPUB and PDF exports never include revisions** — unchanged, `spec.md`'s original
  requirement, not touched by this task.
* **One file per field, not per revision** — `handoff.md` §8's explicit rejection of
  "hundreds of zip entries" for a heavily-edited scene.
* **`includes_revisions` lives in `data/manifest.json`** alongside the existing
  `includes_media` flag — same file, same pattern, confirmed this session
  (`StaticSiteExporter.php:108`, `'includes_media' => $includeMedia`).
* Read `app/Services/StaticSiteExporter.php` in full before writing this task's code —
  this plan file only confirms the toggle's *existence and shape* (`includes_media`),
  not every method this task will touch; the exact insertion points for the per-entity
  `revisions/*.json` writes need to be located against the live `readEntityDescriptors`/
  entity-walking loop, not guessed.

## Consult

* `expanded/architecture.md` — "Import/export" section.
* `handoff.md` §8.
* `app/Services/StaticSiteExporter.php`, `app/Http/Requests/ExportRequest.php`,
  `app/Http/Controllers/ExportController.php` — read all three fully before starting;
  only `ExportController.php` and the manifest emission were read this session.

## Tests

* Exporting with `include_revisions=false` (or the request field omitted) produces a
  zip with `includes_revisions: false` in the manifest and no `revisions/` directories.
* Exporting with `include_revisions=true` produces `includes_revisions: true` and, for
  an entity with N revisions on a field, a single `revisions/<field>.json` containing
  all N as an array (assert the count, not just file existence) — not N separate files.
* EPUB and PDF export tests (existing suites) are unaffected — no new
  `include_revisions` option leaks into those export paths (regression guard, quick to
  assert given those exporters are separate classes).
* A project with zero revisions on a field (a fresh scene never autosaved) exports
  cleanly with no `revisions/` entry for that field, not an empty-array file (avoid
  emitting empty JSON for fields that were never touched).
