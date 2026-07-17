# Advanced search UI revision — resolution log

Implemented directly from the draft spec (user directive: quick win, no
expansion/plan phases). Read `.specs/shipped/advanced_search/resolution-log.md`
first — this feature revises that one's results layout.

## Feedback & decisions

- **One row per matched entity** replaces the previous one-row-per-matching-field
  model. `SearchResultRow` now carries `array $fieldLabels` (+ a `matchedFields()`
  join helper) instead of a single `fieldLabel`; `ProjectSearch::rowsFor()`
  collapses the per-field loop into per-entity accumulation.
- **The single "text preview" cell uses the first matching field's snippet**, in
  the entity's declared field order (name → description → contents → notes). The
  spec said "the text preview" (singular) without picking a field; first-match
  was chosen as the simplest predictable rule — the "Matched in" column tells the
  reader where else the terms appeared. Revisit if users expect the preview to
  skip the name field (whose text already sits in the Name column).
- **The view button links to the edit page** — entities have no separate show
  page, so "view" and the name link share the same URL. A new `x-icon-view-link`
  component (eye icon) mirrors `x-icon-edit-link` for index-row use.
- Sections keep their `<h2>`; each entity type's table gets an `<h3>` above it.
  The `md:grid-cols-3` grid is gone entirely; tables stack full-width.

## Deviations from the spec

- None.

## Issues → resolutions

- **Tests keyed off the old grid markers had to move to `<table` counts.** The
  previous suite used `md:grid-cols-3` (results-only class) and `scope="col"`
  counts (one heading per column) as render markers. The new tables emit four
  `scope="col"` headings each, so counting them no longer maps 1:1 to "rendered
  entity types"; `<table` does (nothing else on the page emits a table) and is
  layout-class-agnostic, so it won't churn on the next restyle.
- **The `w-full` preview cell squeezed the Name column into a wrap-per-word
  sliver** — found only in the browser screenshot (all 30 tests were green; a
  test cannot see column widths). Fixed with `min-w-48` on the name cell, which
  required an `npm run build` because that class had never been used before and
  so was absent from the built CSS (the same silent-failure class of bug as the
  original feature's `bg-sun-200` — verified present in `app-*.css` after the
  rebuild, and re-screenshotted).
- Per the shipped feature's "class silently does nothing" lesson, the classes
  used by the new markup (`space-y-2/4`, `w-full`, `align-top`,
  `whitespace-nowrap`) were verified present in the built
  `public/build/assets/app-*.css`, and `public/hot` confirmed absent, before
  browser verification.
