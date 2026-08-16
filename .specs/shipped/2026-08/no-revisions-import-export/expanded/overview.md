# Overview

Revision history leaves the archive contract. The `.zip` becomes the manuscript plus its
world — never its edit trail.

## Why

- Revision values are **whole field values, one per moment**. A heavily-edited scene multiplies
  the archive by its own history; the size cap (`ImportSetting.max_archive_kilobytes`) is spent on
  data nobody reads.
- Imported rows are **unprunable by design** (`Revision::prunable()` only sweeps `origin:
  automatic`). Every import is a permanent ratchet on the `revisions` table, and the only release
  valve is the `imported` purge category — a whole feature branch existing to undo an import.
- The replay is the most delicate code in the importer: oldest-first ordering, summary
  recomputation against the previous row, `save_id` remapping, per-`FieldKind` sanitizing. All of
  it exists to reconstruct data that is derived from edits that happened on another install.

## Goals

- Export writes no `revisions/<field>.json` and no `includes_revisions` manifest key.
- Import creates no `Revision` rows. Ever, from any archive.
- `RevisionOrigin::Import` and `RevisionPurger::CATEGORY_IMPORTED` are deleted — with no producer,
  the origin is a dead branch in every `match` on the enum.
- Archives exported before this change still import, with their `revisions/` directories ignored
  like `book/`.

## Non-goals

- No change to autosave, save points, prune, revert/undo, or the history UI beyond the two enum
  cases disappearing.
- No change to how an imported project's history *starts*: `RevisionRecorder::ensureBaseline()`
  writes a `baseline` row the first time a writer touches a field, exactly as on a fresh project.
- EPUB/PDF export never carried revisions; untouched.

## Acceptance criteria

| # | Criterion |
|---|---|
| 1 | The export form has one toggle (**Include images & files**); `include_revisions` is not accepted, not validated, not rendered. |
| 2 | No archive entry path contains `revisions/`; `data/manifest.json` has no `includes_revisions` key. |
| 3 | Importing a **v1/v2 archive that contains** `revisions/<field>.json` succeeds and leaves `Revision::count() === 0` for the new project. |
| 4 | `RevisionOrigin` has four cases; `RevisionPurger::CATEGORIES` has three. The admin storage panel shows no *Imported* row and `revisions:purge --category=imported` fails with the unknown-category error. |
| 5 | A migration leaves no `revisions` row holding `origin = 'import'`. |
