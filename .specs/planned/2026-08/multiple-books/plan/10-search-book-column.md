# 10 — Name the book on search results

**Depends on:** 06.

## Scope

- `ProjectSearch`'s `Acts` domain query swaps `where('project_id', …)` for the book walk.
  `Chapters` / `Scenes` already go through `Project::chapterQuery()` / `sceneQuery()`.
- `App\Support\SearchResultRow` carries the book; `x-search.result-row` renders it on
  act / chapter / scene rows only.
- Eager-load the book on those three domains.

**Not in scope:** book-scoped search, or a book filter on the search form. Search stays
project-wide.

## Key decisions

- **Search stays project-wide.** The codex is shared, and a writer searching a series wants
  every hit. Without the book named, a hit in a three-book project is unlocatable.
- The fixed-query-count guarantee is part of the design: the whole search stays a fixed number
  of `SELECT`s regardless of match count. Eager-load, never lazy-load per row.
- Use `displayName()`, never `->name`.

## Consult

`expanded/architecture.md` → *Search stays project-wide*.

## Tests

- `SearchTest`: an act, chapter and scene hit each name their book; plotline, event and codex
  rows do not.
- The existing fixed-query-count assertion still passes with the eager-load in place.
