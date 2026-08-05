# 02 — Atomic cutover

Wire the builder live and convert every route-resolvable in-project page in one change. This
task is **forced atomic**: the instant the composer + band land, all in-project routes render
breadcrumbs and their old header-slot title is suppressed, so header-slot removal + heading
addition must land together or pages go headless (see `00-overview.md` invariant).

## Scope

- **Composer**: in `AppServiceProvider::boot()`, on the existing
  `['layouts.navigation','layouts.app']` composer, add
  `->with('breadcrumbs', new Breadcrumbs($navigation))`.
- **Layout band** (`resources/views/layouts/app.blade.php`): when `! $breadcrumbs->isEmpty()`,
  render a two-column band — left `<x-breadcrumbs :items="$breadcrumbs" />`, right the reserved
  (empty) `$headerActions` slot; else fall back to the current `@isset($header)` band. Declare
  `headerActions` as a named slot on `x-app-layout`, documented as intentionally empty. Markup
  in `expanded/ui.md → Header band`.
- **Convert in-project pages**: remove `<x-slot name="header">` (title + "Back to X") and add
  `<x-page-heading>` above the content column, for every in-project view whose route resolves a
  project via a route param: story/acts/chapters/scenes/plotlines/events/codex/codex-attributes
  (index + create + edit), `projects.story.index`, `projects.search.index`,
  `projects.show` (dashboard — one-item band, heading = project name), and the
  `projects.revisions.index` browser. Full list: the in-project subset of the 44
  `x-slot name="header"` views.

## Explicitly not in scope

- **Revisions history/compare** (`revisions.index/compare/field/field-compare`) — task 03.
  Leave their header slots as-is; the builder yields empty for them so they fall back and stay
  non-headless.
- **Non-project pages** (dashboard root `/dashboard`, profile/*, projects create/edit,
  admin/*) — leave their header slots untouched; the builder yields empty there.
- Right-column action buttons — future spec.

## Depends on

- 01 (value objects + components).

## Key decisions (from grill / spec)

- Band renders breadcrumbs **or** the header slot, never both. Breadcrumbs replace both the
  page title and the "Back to X" link.
- Each converted page keeps a visible document heading via `<x-page-heading>` (breadcrumbs are
  a `<nav>`). Where a body heading already exists, use `x-page-heading` for it rather than
  adding a second.

## Tests

- `tests/Feature/BreadcrumbsTest.php` — per `expanded/testing.md`:
  - Trail shape per section (codex index/edit, scenes create, events edit, revisions.index
    browser, search, dashboard one-item).
  - Section crumbs render as text with no `href`.
  - **No band off-project**: `dashboard`, `profile.edit`, an `admin.*` page render no
    `aria-label="Breadcrumb"` and still show their header slot.
  - A11y: exactly one landmark, one `aria-current="page"`, `aria-hidden` separators.
  - Dynamic leaf is the model's (rename entity in factory → leaf changes).
  - **403 no-leak**: non-owner on an in-project route gets 403 and the entity label is absent.
- Grep the test suite for `Back to` and drop assertions on removed header-slot links.
