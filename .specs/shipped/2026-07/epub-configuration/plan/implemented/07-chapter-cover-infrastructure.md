# 07 — Chapter cover infrastructure

**Model:** Opus (cascade-delete orphan cleanup pitfall + content-sniffed import).

## Scope
Give chapters their own cover image, mirroring the project cover via `CoverImageService`. This is
the **app-side** infrastructure only; rendering the cover into the epub is task 12.

- Migration: `cover_image` string nullable on `chapters`. Add to `Chapter::$fillable`.
- Chapter edit UI (`resources/views/chapters/edit.blade.php`): add `enctype="multipart/form-data"`,
  a cover upload + preview + a "remove cover" checkbox, mirroring the project-edit cover block.
  Hints from `CodexMediaRules::imageHint()`.
- `ChapterController@update`: validate the file with `CodexMediaRules::coverRules()` (add to the
  chapter Form Request); store/replace/remove via `CoverImageService`, deleting the previous file
  on replace/remove. Authorize `update` on the project (walk `$chapter->act->project`), mirrored
  in the request.
- **Orphan cleanup on cascade delete.** project→act→chapter cascades at the DB level and bypass
  model events (the documented codex-media pitfall). Purge chapter cover files the same way:
  extend the `Project::deleting` purge (and/or an `Act::deleting`) to delete surviving chapters'
  cover files, plus a `Chapter::deleting` hook for the single-chapter delete path. No leaked files.
- **Archive:** export each chapter's cover file into the archive media tree and re-import it;
  extend `ImportRules`/`ArchiveValidator` so chapter covers are inside the allow-listed
  arborescence and **content-sniffed** (a forged image rejects the archive). Round-trip test.

Does **not**: render the cover page in the epub (task 12). Does **not** add per-scene images (V2).

## Depends on
06 (`CoverImageService`), 02 (manifest version already bumped; add the cover entry under it).

## Key decisions already made
Overview #5 + the chapter-cover confirmation: full pipeline in v1. Cleanup must survive cascade
deletes; import must content-sniff the file.

## Docs to consult
`../expanded/data-model.md` §Q3/covers, `../expanded/open-questions.md` Q3, the media-lifecycle
notes in `documentation/architecture.md`, current `ProjectController` cover flow.

## Tests to add
`ChapterTest`: upload sets `cover_image`; replace deletes the old file; remove clears it;
non-owner ⇒ 403; invalid file ⇒ `assertSessionHasErrors`. Cascade: deleting the project/act
deletes chapter cover files off disk (assert the disk); deleting a single chapter deletes its
file. Round-trip: a chapter cover survives export→import; a forged cover rejects the archive.
