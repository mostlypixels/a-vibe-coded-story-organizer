# 12 — One EPUB per book, with the appendix filter

**Depends on:** 11.

## Scope

- `EpubExporter::export(Book $book): string`. Metadata, cover, the four matter pages, the
  language and `PublicationSetting` all read off the book.
- `EpubExportController` and `EpubExportRequest` take `book_id` and authorize through
  `$book->project`.
- The Export-ebook page's picker becomes a book `<select>` grouped by `<optgroup>` per project.
- The download filename slugs the book's `displayName()`.
- `EpubExportException` ("nothing to export") is per book — a project with one empty book still
  exports its others.
- **The appendix filter**: entries come from `scene_codex_entry` joined to `Book::sceneQuery()`,
  then the `appendix_entry_types` filter. A book that references nothing gets **no appendix
  pages**, never the full codex.

**Not in scope:** the project `.zip` (task 13).

## Key decisions

- The codex stays project-scoped; only the appendix's *selection* is book-scoped. Unfiltered,
  book 2's appendix lists book 3's characters — a spoiler in a published file.
- Title and `dc:title` use `displayName()`, so a sole unnamed book publishes under the project
  name.
- Existing invariants hold: `validatePackage()` stays a hard gate inside `export()`; rich HTML
  always goes through `RichText::toXhtmlFragment()`; scene bodies never render through
  `Scene::renderedContents`; formatting choices live on enums.

## Consult

`expanded/export-import.md` → *EPUB*; `documentation/epub-export.md`.

## Tests

- `EpubExportTest`: one book exports without the other's content; each book carries its own
  language, author and ISBN; a non-owner `book_id` is a 403.
- The appendix lists only entries the book's scenes reference, and is absent when none are.
- **`defaults_v1_regression` re-baselined deliberately.** A single book carrying the same
  metadata must still produce byte-identical output once OPF timestamps are normalised. If it
  does not, something moved that should not have — investigate before re-baselining.
