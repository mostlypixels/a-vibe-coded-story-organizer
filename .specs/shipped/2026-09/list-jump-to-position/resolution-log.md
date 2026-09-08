# List jump to position — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

> [!IMPORTANT]
> An **exception log, not a work journal**. A task that went to plan gets no entry — the
> diff and the task file already record what was built. Bullets under the headings below,
> root cause first, no per-task sections.

## Feedback & decisions

- **Go to always shows the whole book** — clears search, clears filter, forces
  `sort=position&direction=asc`, always, not only when the target has no matches. Chosen
  over "jump within the search results" because the same button would otherwise do two
  different things depending on data the writer cannot see. This is what removed the
  filtered-jump machinery: no direction handling, no `exists()` check, no empty-jump flash.
  (Grill, 2026-09-08.)
- **One select, two buttons** — **Filter** and **Go to** over the existing chapter/act
  dropdown, rather than a second Go-to select. Two dropdowns of 400 chapters side by side
  is clutter, and pairing the buttons makes the filter-vs-jump difference legible.
  Consequence: `x-index-toolbar` needs no `after` slot and is not modified.
- **The redirect carries a `#chapter-<id>` fragment.** Without it, a group whose first row
  is the last row of a page lands off the bottom of the screen — right page, still hunting.
  The prototype hid this by scrolling with JS, which a real page load does not do. No
  JavaScript and no `scroll-margin`: no table in this app has a sticky header.
- **Resume / "where I left off" was cut before planning.** The prototype showed that
  landing mid-list unasked is as likely to confuse as to help, and the meaning of "where I
  left off" is unsettled. Hence no preference column and no migration. See the source
  spec's *Settled by prototype*.
- **The source spec's ordering premise was stale.** It claims
  `SceneController::chaptersFor()` orders by `name`; it already orders in story order, as
  does `ChapterController::actsFor()`. The "one-line fix" was done before this spec was
  written. Nothing in the plan repeats it.

## Deviations from the spec/plan

- **The highlighted row is a tint (`bg-highlight/25`), not the solid
  `bg-highlight text-highlight-content` the UI doc names.** The solid fill was built
  first and driven in a browser: on the dark preset every cell keeps its own colour
  (`text-content-muted`, the Draft and Unassigned badges, the icon buttons), so the
  row became unreadable. A tint keeps each cell's intended contrast in both themes,
  and the `border-l-4 border-accent` marker still carries the non-colour signal.
- **Go to renders before Filter, not after it.** `x-index-toolbar` puts its slot
  ahead of the Filter button, and the plan forbids touching the component. The pair
  still reads as a pair.
- **The scene toolbar carries a hidden submit button as the form's default.** The
  browser submits the *first* submit button when Enter is pressed in the search box,
  and that is now Go to — pressing Enter would have jumped and cleared the search.
  The hidden button submits without `jump`, so Enter still filters.
- **The missing-event marker wins over the highlight marker** on the `#` cell. It
  reports a problem in the data; the highlight only reports where you landed.

- **`JumpsToListPosition` has no tests of its own.** It calls `route()` and
  `redirect()->route()` against real named routes and needs a book-scoped
  `Collection` of groups — a fake route would test nothing real. Task 03's
  `SceneController` wiring exercises it (no-`jump`, foreign-id, and valid-id
  cases) instead, as the task file allowed.

## Issues → resolutions

- **The range line reads "Chapter 1 — Chapter 1 - The tragic backstory" on the seeded
  demo book.** Not a bug: the line is `Chapter :number — :name`, and the Melusine seed's
  chapters are themselves named "Chapter 1 - …". Real chapter names read correctly.
- **`ChapterController::actsFor()` ordered by `position` only**, with no `id`
  tie-break, while `index()` orders `acts.position, acts.id`. Two acts sharing a
  position would have sorted differently between the dropdown/jump list and the
  index's own query, landing a jump a page off. Fixed by adding `orderBy('id')`
  to `actsFor()`, matching `index()` exactly. Caught by task 01's ordering-drift
  guard test pattern, reused here for acts.
