# 03 — Pagination bar and the plain lists

## Scope

- `resources/views/components/pagination-bar.blade.php`, `@props(['paginator'])`.
  One row, always rendered, below the table:
  - Left: `Showing :first-:last of :total`. An empty list shows 0; the bar never
    disappears.
  - Middle: `$paginator->links()`. Laravel's default Tailwind view already
    renders on `revisions/index` and `search/domain` — publish nothing to
    `resources/views/vendor/pagination`.
  - Right: a `PATCH` form to `preferences.page-size.update` holding an `x-select`
    of `PageSize::sizes()`, `onchange="this.form.requestSubmit()"`. No Apply
    button. Copy the shape of `x-story-mode-switch`.
- Paginate four indexes and drop the bar into their views:
  `TagController`, `CodexAttributeController`, `EventController`,
  `PlotlineController`.

## Depends on

02 (the route the select posts to).

## Not in scope

- Codex entries — task 04.
- Scenes, chapters, acts, books, and the move-button fix — task 05.

## Key decisions

- The edit is `->get()` → `->paginate(PageSize::resolve($request->user()?->page_size))->withQueryString()`,
  at the end of the existing chain. `withQueryString()` carries `sort`,
  `direction`, `search` and the filters into the page links.
- Do not add a hidden `page` field to `x-index-toolbar`. It submits none, so a
  filter change resets to page 1 for free.
- Tags and codex attributes paginate too. The rule is "every list, always"; an
  exception is a second rule for the next person to remember.
- `TagController::index` and `CodexAttributeController::index` take no `Request`
  today. Add the parameter.
- The bar goes below the table only, matching where `revisions/index` puts
  `->links()`. `x-table-empty` keeps its message; the bar sits under it.

## Consult

- `expanded/ui.md`
- `expanded/architecture.md` → Index actions, Must stay unpaginated

## Tests

Start `tests/Feature/ListPaginationTest.php`. Use one data-provider test over
`[route name, factory, parent]` rather than four copies.

- Each of the four indexes hands a `LengthAwarePaginator` to its view.
- 3 rows: the bar renders, `total()` is 3.
- 0 rows: the bar renders, no error.
- Over one page: page 1 is full, page 2 holds the remainder.
- `?page=2&sort=...&direction=...&search=...` — the page-2 link keeps all of them.
- A non-owner gets 403 on `?page=2`.
- Set 250 on one list, load another: 250 there too.
