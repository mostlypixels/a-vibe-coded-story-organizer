# 04 — Numbering restarts per book

**Depends on:** 03.

## Scope

- `StoryNumbering::forProject(Project)` → `forBook(Book)`. `fromActs(Collection)` is unchanged —
  it already takes a tree.
- Every caller passes a book: `ActController@index/edit`, `ChapterController`, `SceneController`,
  `StoryController`, `StaticSiteExporter`, `EpubExporter`.

**Not in scope:** the `#` column's book-scoped *lists* (tasks 06 and 09 re-nest the pages);
here the numbering source changes and the existing pages keep rendering.

## Key decisions

- Numbering is book-wide. Act 1 of book 2 is Act 1, and its chapters and scenes restart too.
- The existing rule binds one level down: build the map from **the whole book's** tree, never a
  filtered or paginated subset. An unknown id still throws rather than rendering blank.
- Three sites deliberately keep raw `position` and must **not** be fed a derived number:
  `StaticSiteExporter::chapterHref()`, the archive `data/` layer, and EPUB scene nav labels.

## Consult

`expanded/architecture.md` → *Numbering is book-wide*; `documentation/architecture.md` →
*Continuous numbering* for the rule it amends.

## Tests

- `StoryNumberingTest`: two books in one project number independently — book 2's first act is
  Act 1, its first chapter Chapter 1, its first scene Scene 1.
- A filtered act list still shows true numbers.
- `ExportTest` / `EpubExportTest`: exported numbers still equal app numbers.
