# 01 — Value objects + components

Build the breadcrumb machinery in isolation. Nothing is wired to the view composer or the
layout yet, so no page changes visually and no page can go headless — this task is safe to
land and verify alone.

## Scope

- `app/Support/Crumb.php` — tiny readonly VO: `label`, `?url` (null = not a link), `current`.
- `app/Support/Breadcrumbs.php` — the builder. Constructed from a `ProjectNavigation`
  (reuse its `routeProject` + `*Active` flags; do **not** re-derive the active section).
  `IteratorAggregate` + `Countable`; `isEmpty()`. Builds off `routeProject` — null → empty.
- `resources/views/components/breadcrumbs.blade.php` — `@props(['items'])`, W3C
  `<nav aria-label="Breadcrumb"><ol>…` markup; see `expanded/ui.md` for the exact structure
  (separators `aria-hidden`, current crumb `aria-current="page"`, truncating leaf).
- `resources/views/components/page-heading.blade.php` — shared `<x-page-heading>` wrapping the
  existing `x-heading` at a consistent level/spacing, for the top of each page's content.

Trail rules (section chain, index vs edit/create leaf, action-precise leaf, codex-type label,
revisions-index) are specified in `expanded/architecture.md → Building the trail`. Implement
all sections **except** the revisions history/compare exception (task 03) — for those routes
`routeProject` is null, so the builder correctly yields empty here and task 03 handles them.

## Explicitly not in scope

- No composer wiring, no layout band, no page edits — all task 02.
- No `{entity}`+`{id}` project resolution — task 03.

## Depends on

- Nothing.

## Key decisions (from grill / spec)

- Off `routeProject`, section crumbs unlinked, action-precise edit/create leaf, one-item
  dashboard trail — see `00-overview.md` binding decisions.
- Leaf label for edit reads the bound model (scene/act/chapter/event→`title`,
  plotline/codexEntry→`name`); codex sub-index label from the active codex type.

## Tests

- `tests/Unit/BreadcrumbsTest.php` — construct `Breadcrumbs` from a `ProjectNavigation` over a
  faked/dispatched request per representative route; assert the ordered crumbs (label, url
  presence, `current`) for: an index, an edit (action-precise leaf from a model), a create,
  codex index (type label), search, dashboard one-item, and a non-project route → `isEmpty()`.
- Component render: `x-breadcrumbs` over a fixture `Crumb[]` renders one
  `aria-label="Breadcrumb"`, one `aria-current="page"`, `aria-hidden` separators, and links
  only on non-current crumbs. `BladeComponentCompilationTest` picks both components up for free.
