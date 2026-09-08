# 03 — Scene list jump

The first user-visible slice, and the one that proves the whole design.

## Scope

**In:**

- `SceneController::index` — `$perPage` extracted, `jumpRedirect()` called right after
  `authorize()`, return type widened to `View|RedirectResponse`.
- `resources/views/scenes/index.blade.php` — **Go to** button, `<optgroup>`s on the chapter
  select, highlighted rows, the anchor id.
- `resources/views/components/table-row.blade.php` — new `highlighted` prop.
- `tests/Feature/ListJumpTest.php` — new.

**Out:**

- the chapter list's act jump → task 04.
- the page-range line → task 05. Do not add `$pageRange` or touch
  `pagination-bar.blade.php` here.

## Depends on

01, 02.

## Key decisions

- **One select, two buttons.** The existing `<select name="chapter">` stays exactly where it
  is; **Go to** is a second submit (`name="jump" value="1"`) dropped into
  `x-index-toolbar`'s **existing** slot, beside **Filter**. `x-index-toolbar` is not
  modified — no `after` slot, no second form.
- **Filter is frozen.** `ListPaginationTest` must pass untouched, including
  `test_scene_page_links_keep_the_chapter_filter_and_the_sort`. If it needs editing, stop —
  something has gone wrong.
- **The anchor goes on the first highlighted row only.** `id="chapter-<id>"` must be unique
  in the document.
- `highlighted` wins over `striped` on `x-table-row`; add the `border-l-4 border-accent`
  left marker so the state is not colour-alone. The existing "no event" cell already uses
  that shape — match it.
- Chapter options become `<optgroup label="{act}">` groups; the flat `Act — Chapter` label
  goes.

## Watch for

`chaptersFor()` is **already** in story order (`acts.position, acts.id, chapters.position,
chapters.id`). The source spec claims it sorts by `name` — that is stale. Change nothing
about its ordering; `ListJump` depends on it matching `index()`'s chain exactly.

The move buttons render only when `$sort === 'position' && request()->filled('chapter')`, so
a jumped-to view has none. Deliberate — leave the condition alone.

## Tests

`../expanded/testing.md` is the list. The ones that must not be skipped:

- lands on the right page; `Location` ends `#chapter-<id>`; no `jump` on the landed URL.
- **the search is cleared even when the target matches it** — the whole point of the
  "always" rule.
- the filter is cleared; the sort is forced from `?sort=name&direction=desc` to
  `position`/`asc`.
- page size 25 vs the default puts the same chapter on a different page.
- a chapter id from another book → plain page 1, no `highlight`, no leak.
- non-owner → 403 on the jump URL.
- **Filter still filters**: `?chapter=<id>` with no `jump` behaves exactly as today, move
  buttons and all.
- highlight marks only that chapter's rows; only the first carries the anchor id; an
  unknown `highlight` marks nothing and does not error.

## Consult

`../expanded/architecture.md` → *Controller changes*, *The fragment*.
`../expanded/ui.md` → all of it.
