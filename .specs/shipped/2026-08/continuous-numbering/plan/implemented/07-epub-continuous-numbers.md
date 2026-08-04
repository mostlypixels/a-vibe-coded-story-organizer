# 07 — Continuous numbers through the EPUB

## Scope

Act and chapter numbers in the EPUB become the derived rank, built with
`fromActs($this->bookTree($project))`. The exporter folds the resolved integer into the data
arrays it already builds, so Blade never calls the lookup itself.

- `actViewData()`, `chapterViewData()`, `actNavTitle()`, `chapterNavTitle()`.
- `renderToc()` inherits both through those nav-title helpers — no separate change.
- Rename the `position` view key to `number` in the export layer, **including the partials**:
  `partials/act-body.blade.php` (`<h1>Act {{ $position }}</h1>`), `act.blade.php` and
  `act-combined.blade.php` (layout titles), `chapter.blade.php` (its document-title
  fallback), `partials/chapter-body.blade.php`. Nothing in the export layer should still call
  a display number "position".
- `ChapterTitleFormat::format()` — rename the `$position` parameter to `$number` and update
  the docblock. No behaviour change.

## Depends on

01, 06. Numbering the full outline only makes sense once 06 stops filtering it.

## Key decisions

- **`sceneNavTitle()` is not touched.** It stays `"Scene {$scene->position}"` — per-chapter.
  It is the fallback label for an untitled scene in the book's nav, and a project-wide count
  ("Scene 147") means nothing to a reader browsing under a chapter heading.
- Export numbers now always equal app numbers, because 06 removed the only thing that made
  them diverge.
- File names are id-keyed (`act-{id}.xhtml`, `chapter-{id}.xhtml`) — untouched.

## Tests

- Chapter headings and nav labels run unbroken across act dividers, in every
  `ChapterTitleFormat`.
- Act numbers are continuous and gap-free after an act is deleted.
- Chapter numbers in the EPUB match the numbers the app shows for the same project,
  including when placeholders sit between written chapters.
- Untitled scenes still read "Scene 1", "Scene 2" within their chapter.
- The in-book TOC page and the EPUB 3 nav document agree with the headings.
