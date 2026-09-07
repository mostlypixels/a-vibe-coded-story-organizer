# 06 — Page total and full total footers

## Scope

The `Total` footer row on the scenes, chapters and acts indexes sums the
collection, which pagination narrowed to one page. It still says "Total". Split
it into two footer rows:

- **Page total** — the existing sums, unchanged. Rename the label only.
- **Full total** — the same aggregates over the whole list the writer is looking
  at, every page included.

Views: `resources/views/{scenes,chapters,acts}/index.blade.php`.
Controllers: `SceneController`, `ChapterController`, `ActController`.

## Depends on

05.

## Not in scope

- Books, events, plotlines, tags, codex attributes and codex entries have no
  `Total` footer. Do not add one.

## Key decisions

- **"Full total" respects the current search and filter, not just the page.** It
  is the total of the list on screen across all its pages — not a book-wide
  figure that ignores the filter. A scenes list filtered to one chapter shows
  that chapter's total.
- Compute the aggregates in the controller with their own queries. Do **not**
  hydrate the full result set to sum it in PHP — that is the cost this whole
  feature removes.
- Build the filtered query into a variable, clone it for the totals, then add
  the aggregates, ordering and `->paginate()`. Clone **before**
  `withCount`/`withSum`: a `select()` after those aliases resets the column list
  and drops them. The trap is already documented in `ChapterController::index`;
  read that comment first.
- Prefer readable Eloquent to raw SQL. A `whereIn(..., $subquery->select('id'))`
  plus `count()` / `sum()` is fine; `selectRaw` with two aggregates is not
  needed.
- Scenes: sum `word_count`. Chapters: count of scenes and sum of their
  `word_count`. Acts: count of chapters and sum of their scenes' `word_count`.
- The footer rows stay inside the existing `@if ($x->isNotEmpty())` guard.

## Consult

- `expanded/architecture.md` → Index actions
- The existing `withSum('scenes as word_count', 'word_count')` comments in
  `ChapterController::index` and `ActController::index`

## Tests

Extend `tests/Feature/ListPaginationTest.php`:

- Scenes: 120 scenes over two pages. Page 1's "Page total" is the first 100
  words; "Full total" is all 120, on both page 1 and page 2.
- Chapters and acts: the same shape for their count and word columns.
- A filtered list: "Full total" covers the filter's whole match set, not the
  book, and not the page.
- An empty list renders neither footer row and does not error.
