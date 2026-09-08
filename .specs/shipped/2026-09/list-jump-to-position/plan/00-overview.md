# List jump to position — plan overview

Reach a chapter on the scene list, or an act on the chapter list, by naming it — landing on
the page that holds it with the whole list intact.

## Execution order

| # | Task | Purpose |
| --- | --- | --- |
| 01 | `list-jump-support-class` | `App\Support\ListJump` — the page arithmetic, alone and unit-testable |
| 02 | `jumps-to-list-position-concern` | The controller concern that turns a Go-to request into a redirect |
| 03 | `scene-list-jump` | Go-to button, redirect wiring, highlight and anchor on the scene list |
| 04 | `chapter-list-jump` | The same for acts on the chapter list |
| 05 | `page-range-line` | "Chapter 214 to Chapter 231" above the pagination bar, both lists |

01 → 02 → 03 → 04 is a dependency chain. 05 depends only on 03 and 04 having landed the
`$pageRange` controller values; it touches a shared component, so it goes last.

## Binding decisions

Settled in the design grill. **Do not re-litigate these.**

- **Go to always shows the whole book.** It clears the search, clears the filter, and forces
  `sort=position&direction=asc`. Always — not only when the target has no matches. There is
  no "jump within the search results" mode and no empty-jump state.
- **One dropdown, two buttons.** The existing `chapter` / `act` select serves both. **Filter**
  keeps today's behaviour byte for byte; **Go to** is a second submit (`name="jump"`) in
  `x-index-toolbar`'s existing slot. No `after` slot, no second form. `x-index-toolbar` is
  not modified.
- **The redirect carries a fragment.** `#chapter-<id>` / `#act-<id>`, anchored on the first
  highlighted row, so the browser scrolls to the group. No JavaScript, no `scroll-margin` —
  no table in this app has a sticky header.
- **`jump` never survives the redirect.** The landed URL carries only `page`, `highlight`,
  `sort`, `direction`.
- **`ListJump` lives in `app/Support`**, beside `PageSize`: one static, no state.
- **Scope is the scene and chapter lists only.** Not acts (3–6 rows), not codex (no story
  order — that is `scoped-search`).
- **No migration, no model change, no new route, no preference column.** The resume /
  "where I left off" behaviour was cut by the prototype; see the source spec's *Settled by
  prototype*.

## Invariants every task must preserve

- **Ordering must not drift.** `ListJump` is correct only while the ordered id list from
  `chaptersFor()` / `actsFor()` matches the index's own `orderBy` chain exactly — including
  the `id` tie-breaks that exist because `position` has no unique constraint. Drift makes a
  jump land a page off, silently. Task 01 owns the guard test.
- **Filter behaviour is frozen.** Both buttons share one select. Every existing filter URL,
  and `ListPaginationTest` in full, must keep passing untouched.
- **Authorization through the owning project.** Both `index()` actions already
  `authorize('view', $book->project)`; the jump redirect is issued after it. A jump id
  outside the book's own groups is treated as absent — a plain page 1, no leak. Every task
  that adds a route-reachable path adds a non-owner 403 test.
- **`StoryNumbering` stays book-wide.** The `#` column is already correct on any page and on
  any filter; nothing here changes it.
- **The pagination bar stays shared.** Task 05 edits a component nine lists render. A list
  that passes no `range` must render exactly as it does today.

## Detail lives in

`../expanded/` — `overview.md` (criteria), `architecture.md` (the parameters, the concern,
the controller wiring), `ui.md` (Blade), `testing.md` (the test list, including the
ordering-drift guard).
