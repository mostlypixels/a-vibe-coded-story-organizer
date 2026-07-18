# 06 — Shared CoverImageService

**Model:** Haiku (pure refactor, guarded by existing green tests).

## Scope
Extract the cover-file handling currently private to `ProjectController` into a reusable service,
so the chapter cover (task 07) and the exporter reuse it instead of duplicating. Pure refactor —
**no behaviour change**.

- New `app/Services/CoverImageService.php`: `store(UploadedFile): string` (→ path on the `public`
  disk under a caller-supplied directory), `delete(?string $path): void` (orphan-safe), and
  `bytes(?string $path): ?string` + `mimeType(?string): ?string` for the epub embed path (read
  straight off `Storage::disk('public')`, never the `/storage` URL). Keep the `public`-disk /
  directory constants here (single source; today they live as `ProjectController::COVER_*`).
- Refactor `ProjectController::storeCoverImage`/`deleteCoverImage` (and the `Project::booted()`
  cover cleanup, if cleaner) onto the service. Refactor `EpubExporter::applyCover()` to read bytes
  via the service.

Does **not**: add chapter covers (task 07) or change the epub cover output. Justified now (CLAUDE.md
"no abstraction before a second caller") because task 07 is the real second caller.

## Depends on
Nothing hard; do before 07 and before the exporter cover work (08).

## Key decisions already made
Overview #5: one shared `CoverImageService` for Project + Chapter + exporter.

## Docs to consult
`../expanded/architecture.md` §B "codex appendix / images" + the cover notes; current
`ProjectController` cover methods and `EpubExporter::applyCover()`.

## Tests to add
No new behaviour, so the guard is **existing green**: `ProjectTest` cover upload/replace/remove
and `EpubExporterTest` cover embedding still pass unchanged. Add a small `CoverImageServiceTest`
(store returns a path under the given dir; delete is null-safe; bytes/mime read a stored file).
