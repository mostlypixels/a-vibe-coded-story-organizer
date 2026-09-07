# List pagination — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

> [!IMPORTANT]
> An **exception log, not a work journal**. A task that went to plan gets no entry — the
> diff and the task file already record what was built. Bullets under the headings below,
> root cause first, no per-task sections.

## Feedback & decisions

- **No `return_to` field.** The size write redirects to `url()->previous()` with `page`
  stripped. The session holds that URL, so there is no user input and no open-redirect
  guard to write. Replaces the `return_to` design in `expanded/architecture.md`.
- **Default stays 100**, not the 50 that `open-questions.md` wavered toward. The pain is
  1600 rows; 100 already solves it, and the size select is right there.
- **All nine lists paginate**, tags and codex attributes included. An exception is a
  second rule for the next person to remember.
- **A row moved up from the top of page 2 lands on page 1 and leaves the view.** Accepted.
  Following the row means computing its new page in four controllers for a rare case.
- **The scene list's 400-option chapter filter stays out of scope.** Its own feature; it
  is the next wall a serial writer hits after this ships.
- Filter changes reset to page 1 (free — `x-index-toolbar` submits no `page`). The bar
  goes below the table only. The chapter filter does not default to the last-edited
  chapter.
- **The `Total` footer shows both numbers, not one.** Asked for during the final pass:
  "Page total" over "Full total", rather than relabelling the page sum alone. "Full
  total" covers the whole filtered list across every page — not the whole book, which
  would ignore the filter the writer just applied.

## Deviations from the spec/plan

- **The books index needed two more edits than "paginate plus the bar".** Its
  `$isLastBook = $books->count() === 1` became `total() === 1` — page count is
  not project count, and a lone book on a later page would have hidden every
  delete button. Its `#` column moved from `$loop->iteration` to
  `$books->firstItem() + $loop->index`, or page 2 restarts at 1. Books have no
  `StoryNumbering`, so nothing else supplied the offset.
- **Two footer rows needed a change to the shared `x-table` component.** Its
  `foot` slot wrapped the cells in one `<tr>`, so a second row was impossible.
  The slot now supplies its own rows, which also touched
  `components/search/result-table.blade.php` — the only other `foot` caller.
- **Two existing N+1 guard tests changed their expected query counts.**
  `ActTest::test_the_acts_index_issues_one_grouped_query_for_word_counts` goes
  2 → 3 and the chapter twin 2 → 4: the full totals add one aggregate query
  each (chapters add two — a count and a sum). Still O(1) per page load.

## Issues → resolutions

- **The `Total` footer row on the scenes, chapters and acts indexes summed the
  page, not the list.** Root cause: the footers call `$scenes->sum('word_count')`
  on the collection, which pagination narrowed. Fixed by task 06: the row is now
  "Page total", with a "Full total" row under it fed by aggregate queries on the
  filtered set. Resolved.
- **The pagination bar printed the row range twice.** Root cause: Laravel's
  default paginator view renders its own "Showing 51 to 64 of 64 results", but
  only when there is more than one page. Fixed: the bar's own range now sits
  behind `@unless ($paginator->hasPages())`, so it fills the single-page gap and
  never doubles up. Resolved.
- **A sort-header click on page 2 kept `page=2`.** Root cause:
  `x-sortable-header` carried the whole query string, unlike `x-index-toolbar`.
  Sorting reshuffles every row, so the old page number points at different rows
  afterwards. Fixed: the header passes `'page' => null`, which
  `http_build_query` drops. Resolved.
- **The move buttons carry `disabled:` Tailwind classes at all times.** A test that
  greps a row block for `disabled` therefore always passes. `ListPaginationTest`
  matches `disabled="disabled"` — the rendered attribute — instead.
- **The scene list shows its move buttons only under a chapter filter and the
  position sort.** A page-2 move-button test on scenes must send both. The acts
  index has no such condition and carries the full first/last-page coverage.
- **Laravel's paginator only draws its ellipsis from 15 pages** (`onEachSide`
  defaults to 3), so a 12-page list printed twelve buttons. The bar calls
  `onEachSide(1)`, which brings the ellipsis in at 11 pages and keeps the bar
  narrow.
- **Laravel's default paginator view hardcodes gray and Tailwind's `dark:`
  variant**, so it does not follow `ThemePreset`. Pre-existing — `revisions/index`
  and `search/domain` already render it — and not touched here. It is the reason
  to publish `resources/views/vendor/pagination` if theming it ever matters.
