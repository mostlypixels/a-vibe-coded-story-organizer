# Task 15 — Zip import: read revision sidecars

## Scope

`App\Services\Import\ProjectGraphImporter` (read this session — has `readHtmlField()`/
`readMarkdownField()`/`readFieldFile()` sidecar-reading helpers already, plus
per-phase `importStory()`/`importCodex()`/etc. methods) gains the ability to read each
entity's `revisions/<field>.json` (when `data/manifest.json`'s `includes_revisions` is
true — check via `ArchiveValidator`'s already-read manifest, same pattern as
`includes_media`) and insert one `Revision` row per array entry for the newly-created
entity, with:

* `created_at` **preserved** from the archive (not rewritten to import time) — so
  history still reads truthfully.
* `user_id` **remapped** to the importing user (the source `user_id` means nothing on
  the destination install).
* `origin` **forced to `import`** on every imported row, regardless of what it was in
  the source archive — exempt from age-pruning (`handoff.md` §8's "why the exemption":
  an import is an explicit act of preservation).

Does **not** include the export side (task 14, already done) or any change to
`ArchiveValidator`'s validation rules beyond what's needed to read the new manifest
flag (this task should not need new *validation* checks — a missing/absent
`revisions/` directory when `includes_revisions` is false is simply "no revisions to
import", not a validation failure).

## Depends on

Task 14 (the file format this task reads), task 1 (`Revision` model), task 3
(`AutosavableFields` to know which fields to look for revisions of).

## Key decisions already made

* **`created_at` is preserved, `user_id` is remapped, `origin` is forced to `import`** —
  all three are explicit, deliberate choices in `handoff.md` §8, not defaults to
  rediscover. Rewriting `created_at` to import time was explicitly rejected ("every
  revision would claim to have been written on restore day, breaking compare-by-date
  entirely").
* **Imported revisions must use `id_maps`** (the existing cross-reference table
  `ProjectGraphImporter` already builds per phase, confirmed this session via its
  `importTimeline(..., array &$idMaps)`/`importStory(..., array &$idMaps)` signatures)
  to attach each imported revision to the *newly created* entity's id, not the source
  archive's id.
* This is genuinely new scope on top of the existing importer, not a one-line addition
  — `handoff.md` §11.4's own warning ("real additional scope... not a toggle") about
  touching this area applies; read `ProjectGraphImporter` in full (not just the grep
  done this session) before estimating this task's real size, and split it further
  during implementation if it turns out to need its own sub-phase in the
  `ImportPhase` enum rather than riding inside an existing phase.

## Consult

* `expanded/architecture.md` — "Import/export" section.
* `handoff.md` §8.
* `app/Services/Import/ProjectGraphImporter.php`, `app/Services/Import/
  ArchiveValidator.php`, `app/Enums/ImportPhase.php` — read all three fully; this
  session only grepped method signatures and the manifest-key constant, not full logic.

## Tests

* Importing an archive with `includes_revisions: true` and a `revisions/contents.json`
  for a scene creates the same number of `Revision` rows on the newly-created scene,
  each with `origin: import`, `created_at` matching the archive's value, and `user_id`
  equal to the importing user (not whatever `user_id` was in the archive).
* Importing an archive with `includes_revisions: false` (or the key absent, for
  backward compatibility with archives from before this feature) imports the project
  with zero revisions and no error.
* A resumed/re-run import (per the existing checkpoint-resume contract documented in
  `ProjectImporter`'s own docblock, read this session) does not duplicate revision rows
  on retry — match whatever idempotency mechanism the existing phases already use.
* Imported revisions are correctly excluded from `Revision::prunable()` (task 1) —
  reuses task 1's own prunable tests' shape but seeded via the import path specifically.
