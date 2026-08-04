# 08 — Chapter numbers in the website book layer

## Scope

`StaticSiteExporter`'s `book/` layer currently shows chapter **titles only** — there is no
number anywhere in it. This task adds them, formatted by the same publication setting the
EPUB obeys.

- `addBookIndex()` — `$toc` chapter entries carry a formatted heading instead of a bare name.
- `addBook()` — the per-chapter page's `chapterTitle` likewise.
- Both go through `ChapterTitleFormat::format()` with the number from
  `fromActs($this->loadBookTree($project))`, so one setting drives both exports.
- Act entries in the TOC take the derived act number too.

## Depends on

01.

## Key decisions

- **`chapterHref()` is untouched.** It keeps `%02d/%02d.html` from raw act position and
  per-act chapter position — that is file identity, and feeding it a continuous number would
  change every previously exported URL.
- The `data/` layer (`'position' => $chapter->position`) is the archive round-trip consumed
  by `ProjectGraphImporter` / `ArchiveValidator`. Untouched.
- `loadBookTree()` already publishes empty chapters as heading-only pages, which is what task
  06 makes the EPUB do. Nothing to change there.
- `publicationSettingOrDefault()` is the same accessor the EPUB uses; a project with no saved
  row gets the default format.
- This makes `expanded/overview.md`'s "the static-site export is byte-identical" acceptance
  criterion false. Existing chapter **URLs** stay identical; the TOC and headings gain
  numbers. Record the correction in `resolution-log.md`.

## Tests

`tests/Feature/ExportTest.php`:

- Href regression: with three acts and reordered chapters, `chapterHref` output is unchanged
  (`01/01.html`, …), and `test_..._chapter_numbering_must_reset_per_act` still passes.
- `book/index.html` shows continuous chapter numbers across the act boundary.
- Chapter page headings carry the number.
- Every `ChapterTitleFormat` drives the website output the same way it drives the EPUB.
- The `data/` JSON still carries per-parent `position`; the import round-trip is unaffected.
- A project with no chapters still exports `book/index.html` and no chapter pages.
