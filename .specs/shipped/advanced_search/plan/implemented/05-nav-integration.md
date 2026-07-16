# Task 05 — Nav integration

## Scope

Add the "Search" link as the **last** item in the primary nav, per the spec's "listed at the
end of the menu":

* `resources/views/layouts/navigation.blade.php`, desktop block: a plain `x-nav-link` (not a
  dropdown — it's a single page, same as the existing "Home" link), placed after the
  Timeline/Codex/Story dropdowns.
* Responsive block: matching `x-responsive-nav-link` in the same trailing position.
* Add `$searchActive = request()->routeIs('projects.search.*');` alongside the other
  `*Active` booleans (both the desktop `@php` block and the responsive `@php` block — they are
  currently duplicated, matching the existing pattern for `$storyActive`/`$timelineActive`/
  `$codexActive`) and pass it to the link's `:active` prop.

This task does not change the dropdown structure of Timeline/Codex/Story, and does not touch
`SearchController`/the view content (tasks 03/04) — it only adds the entry point.

## Depends on

* Task 03 (the `projects.search.index` route must exist to link to and to `routeIs()` against).

## Key decisions already made (binding, see `00-overview.md`)

* Search is a single top-level link, not a dropdown — consistent with "Home" being the only
  other non-dropdown top-level item.
* It goes last, after Story (the current last dropdown).

## Docs to consult

* `expanded/architecture.md` → *Route* section, which specifies this exact placement.
* `documentation/architecture.md` if it documents the nav's active-state pattern in more
  detail than what's visible directly in `navigation.blade.php`.

## Tests

Extend `tests/Feature/NavigationTest.php`, reusing its existing `assertLinkIsCurrent`/
`assertLinkIsNotCurrent` helpers (which assert on `aria-current="page"` and `href`, not
Tailwind classes — follow that same convention, do not add a class-based assertion):

* Visiting the search page marks the Search link as current (`aria-current="page"`) and
  leaves Home/Timeline/Codex/Story links not-current.
* Visiting a non-search page (e.g. an existing Story/Codex page already covered by this test
  file) leaves the Search link present but not current.
* The Search link's `href` resolves to `route('projects.search.index', $project)`.
