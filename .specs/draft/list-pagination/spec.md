---
status: draft
---

# List pagination

No entity list paginates. Every `index` action calls `->get()` and renders the whole
table. At demo size nothing looks wrong; the Melusine book has 29 scenes.

A serial writer with 400 chapters has about 1600 scenes, 400 chapters and 300 codex
entries per type. Her scene list is 1600 rows, rebuilt on every visit. The word
"paginated" already appears in two comments (`SceneController::index`,
`ChapterController::index`) describing a page size that was never added.

Two screens do paginate, both by hand: the revisions history and the search "see all"
page. Neither is a pattern the lists can copy — the search page pages a collection that
PHP already matched, because matching cannot use SQL `LIMIT`.

## Goals

- Paginate every entity list: scenes, chapters, acts, codex entries, events, plotlines,
  books, tags, codex attributes.
- **Every list paginates, always**, even one with four rows. The bar is always there and
  the behaviour is the same everywhere. A list that sometimes pages and sometimes does not
  is two behaviours to learn.
- Page size defaults to 100. The pagination bar offers 50 / 100 / 250 / 500.
- The chosen size persists. It is the writer's preference, and it holds across lists and
  across sessions.
- Page links keep the current sort, direction, search and filters.
- Row work moves to the page: a list must not compute anything per row for rows it does
  not show.
- Story numbers keep counting from the whole book, not from the page. Both existing
  comments already promise this.

## Non-goals

- No infinite scroll, no "load more". Numbered pages.
- No admin-wide page size. The size is the reader's, not the installation's.
- No per-list size. One preference, every list.
- No change to the search page or the revisions history. They page already and their
  `per_page` stays in their own config.
- No change to the story overview, which has its own chapter-at-a-time mode.
- No new sorting or filtering.

## Approach

- Swap `->get()` for `->paginate()` in each `index` action, then `->withQueryString()` so
  the existing sort and filter parameters survive a page change. The queries already
  filter and order in SQL, so this is real `LIMIT`/`OFFSET`, not a PHP slice.
- Persist the size on the user, beside the other interface preferences already in
  `users` (`theme_slug`, `ui_scale`, `ui_font` and the rest). Changing it in the bar is a
  small write, then a redirect back to the list.
- Resolve the size through one place, the way `ThemePreset::resolve()` and
  `FontChoice::resolve()` do for themes and fonts: an allow-list of the four sizes, with
  the default for anything else. Never trust the submitted number.
- A shared pagination-bar component: the page links and the size select, used by every
  list.
- Fix the per-row work the page size exposes. `SceneController::index` builds a duplicate
  name suggestion for every row from every scene name in the project — 1600 lookups over a
  1600-name list, to fill a box the writer may never open.

## Open ends

- Whether changing the page size, or a filter, returns to page 1. Landing on page 12 of a
  list that now has three pages has no obvious right answer.
- Whether the size control writes over a form post or a link with a query parameter that
  the app then stores.
- Whether the scene list should also default its chapter filter to the writer's last
  chapter, as the serial-writer notes ask, or whether that belongs in its own feature.
