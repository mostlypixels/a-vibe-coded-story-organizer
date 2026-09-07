# List pagination — plan overview

Nine entity indexes call `->get()`. Give each one `->paginate()`, one shared
pagination bar, and one per-user page-size preference.

Read `expanded/` for detail. This file is the manual; it is never implemented and
never moves.

## Execution order

| # | Task | Purpose |
|---|---|---|
| 01 | `page-size-foundation` | `config/pagination.php`, `PageSize` support class, `users.page_size` column |
| 02 | `page-size-preference-write` | The route, Form Request and controller that store the chosen size |
| 03 | `pagination-bar-and-plain-lists` | The `x-pagination-bar` component; paginate tags, codex attributes, events, plotlines |
| 04 | `paginate-codex-entries` | Codex entry index; the per-row `DuplicateName` cost |
| 05 | `paginate-story-lists` | Scenes, chapters, acts, books; the move-button fix |

02 depends on 01. 03 depends on 02. 04 and 05 depend on 03.

## Binding decisions

Settled in the spec docs or the grill. Do not re-open them.

- Sizes are 50 / 100 / 250 / 500. Default 100.
- One preference for the whole user, every list. No per-list size, no
  installation-wide override.
- Every list paginates, always, at every row count — including tags and codex
  attributes. No exceptions; an exception is a second rule to remember.
- The size is written by a `PATCH` form, never by a `?per_page=` query parameter.
  A GET must not write to the database.
- **No `return_to` field.** After the write, redirect to `url()->previous()` with
  `page` stripped. The previous URL comes from the session, so there is no user
  input to guard and no open-redirect check to write.
- Changing the size, or any filter, returns to page 1.
- The bar sits below the table only, never above.
- Search (`config/search.php`) and revision history (`config/revisions.php`) keep
  their own `per_page` and their own hand-built paginators. Do not refactor them
  to share this feature's config. If their tests fail, this feature has leaked.
- The story overview keeps `StoryOverviewMode::Chapter`. Not touched.
- Out of scope: the scene list's 400-option chapter filter, and defaulting that
  filter to the last-edited chapter. Both are their own features.

## Invariants every task must keep

- **Story numbers count from the book.** `StoryNumbering::forBook($book)` is
  book-wide by contract. Never build it from the paginated set.
- **Full sets stay full.** Move-target lists (`$destinationActs`,
  `$destinationChapters`, `$destinationBooks`), filter dropdowns
  (`chaptersFor()`, `actsFor()`, plotline and tag selects) and the
  `DuplicateName` `$names` pluck must offer every row, not the page's.
- **Authorization runs before pagination.** A non-owner gets 403 on `?page=2`
  exactly as on page 1.
- **A submitted size off the allow-list resolves to the default and is not
  stored.** The allow-list lives inside `PageSize::resolve()`; nothing else
  re-implements it.
- **Nothing may `select()` after a `withCount`/`withSum`** — the alias reset trap
  already documented in `ChapterController::index`. `->paginate()` goes at the
  end of the chain.
- **Per-row work is per rendered row.** A list must not compute anything for rows
  it does not show.
