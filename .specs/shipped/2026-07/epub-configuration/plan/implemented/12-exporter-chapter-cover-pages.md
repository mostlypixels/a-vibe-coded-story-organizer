# 12 — Exporter: chapter cover pages

## Scope
Render a full-page chapter cover image inside the epub when a chapter has one and the toggle is on.

- Gate on `$settings->include_chapter_covers`. For each chapter (in `body`, in `filteredTree`
  order) that has a `cover_image`, emit a full-page cover XHTML page **immediately before** that
  chapter's content page, and embed the image bytes via `CoverImageService` /
  `Storage::disk('public')` + the library's `addImageFile(...)`. A `cover_image` pointing at a
  missing file is skipped silently (as the project cover already does).
- New `resources/views/exports/epub/chapter-cover.blade.php` (full-bleed image page; styled in
  `styles.css`). The cover page is a nav sibling/child of its chapter per the existing two-level
  nesting (decide during impl; keep well-formed + schema-valid).

Does **not**: add per-scene images (V2) or project cover changes (task 08 owns the project cover).

## Depends on
07 (chapters have `cover_image` + files on disk), 08 (settings threaded), 06 (`CoverImageService`
byte-read). Best after 09/10 so the chapter page/nav structure is settled.

## Key decisions already made
Overview #3 (`include_chapter_covers` defaults **off** ⇒ no change by default), #5 (bytes via the
shared service; missing file skipped, never fatal).

## Docs to consult
`../expanded/data-model.md` covers, `../expanded/architecture.md` §B, the library `addImageFile`
usage patterns already in `EpubExporter`.

## Tests to add
Extend `EpubExporterTest`: with `include_chapter_covers=true` and a chapter cover set, the package
contains the cover image + a cover page positioned before that chapter; toggle off ⇒ absent even
when the cover exists; a chapter whose `cover_image` file is missing is skipped (export still
succeeds + validates). Defaults===v1 still green.
