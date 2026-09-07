# Overview

Nine entity indexes call `->get()` and render every row. At serial-writer scale
(1600 scenes, 400 chapters, 300 codex entries per type) each visit hydrates the
whole set, and `SceneController::index` runs `DuplicateName::suggest()` once per
row against a project-wide name list.

## Goals

- `->paginate()` on every entity index: scenes, chapters, acts, codex entries,
  events, plotlines, books, tags, codex attributes.
- The bar renders on every list, at every row count. One behaviour to learn.
- Sizes 50 / 100 / 250 / 500, default 100.
- One page-size preference per user, across all lists, across sessions.
- Page links keep sort, direction, search and filters.
- Story numbers keep counting from the book (`StoryNumbering::forBook()` already
  does; the two existing comments promise it).

## Non-goals

Restated only where the expansion adds a boundary: search (`config/search.php`)
and revision history (`config/revisions.php`) keep their own `per_page` and their
own hand-built `LengthAwarePaginator`. Neither is refactored to share this
feature's config. The story overview keeps `StoryOverviewMode::Chapter`.

## User stories

- As a serial writer, I open Scenes on a 400-chapter book and get 100 rows, not 1600.
- As any writer, I set 250 once on the scenes list and every other list is 250 too.
- As any writer, I sort by Title, go to page 4, and the sort holds.
- As any writer, the `#` column on page 4 of Scenes reads 301-400, not 1-100.

## Acceptance criteria

| # | Criterion |
|---|---|
| 1 | Every index in the list above returns a paginator to its view. |
| 2 | The bar renders with 3 rows and with 3000 rows. |
| 3 | A submitted size outside the allow-list resolves to 100 and is not stored. |
| 4 | Page 2 of a sorted, searched, filtered list keeps all three. |
| 5 | Changing the size returns to page 1 of the same list, filters intact. |
| 6 | Scene `#` on page N is the book-wide number. |
| 7 | Move up/down is disabled only on the globally first/last row, not the page's. |
| 8 | `DuplicateName::suggest()` runs once per rendered row, not once per project row. |
