# Breadcrumbs — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

> [!IMPORTANT]
> An **exception log, not a work journal**. A task that went to plan gets no entry — the
> diff and the task file already record what was built. Bullets under the headings below,
> root cause first, no per-task sections.

## Feedback & decisions

- Edit/create leaf is **action-precise**, stating the operation. **Post-ship the leaf form was
  revised** (supersedes the original `Edit character — Mélusine` / Title-Case decisions): the
  edit leaf names the bound model's **id** (`Edit chapter 1`), not its name, and the thing is
  **lowercase** for every editable entity (acts, chapters, scenes, plotlines, events, codex
  entries, codex attributes); create is `New scene`. Id chosen "for now" — a provisional
  identifier matching the URL; the label attribute is deliberately not read. Revisions
  history/compare leaves keep naming the entity (they are read views, not edit pages).
- Missing document heading resolved with a shared **`<x-page-heading>`** above each page's
  content column (breadcrumbs are a `<nav>`, not a heading).
- **Atomic cutover** chosen over opt-in scaffolding or transitional double-render: the builder
  is route-driven and goes live for all in-project pages at once, so machinery + page
  conversion land together (task 02) to avoid any headless page.
- Dashboard (`projects.show`) shows a **one-item** band (`Dashboard`, current).
- Revisions history/compare pages convert in a **separate task** (03) via a view-supplied
  trail tail; they fall back to their header slot during the atomic cutover so they never go
  headless. Leaf names the entity + title, not a bare "History".
- Admin left **out of scope** (own `AdminNavigation`); handled automatically by the
  `routeProject`-null fallback.

## Deviations from the spec/plan

- **Dashboard (`projects.show`) header extras relocated, not dropped.** The band's right
  column (`$headerActions`) is reserved-empty per spec, but the dashboard header carried a
  word count + "Edit Project" link. Moved them into the page body beside the `x-page-heading`
  row; switched the word count from `variant="band"` to `variant="muted"` since it now sits on
  the page surface, not the dark band. This one page uses `x-heading level="1"` in a flex row
  rather than `x-page-heading` (which has no actions affordance).
- `Breadcrumbs::__construct()` takes `(ProjectNavigation $navigation, Request $request)`, not
  just `$navigation` as architecture.md's illustrative composer line shows — it needs the
  request for the route name and bound models, and this matches `ProjectNavigation`'s own
  constructor. Task 02's composer wiring must call
  `new Breadcrumbs($navigation, request())`.

## Issues → resolutions

- **Plain crumbs were invisible on the band (blue-on-blue).** The band sets `bg-nav-raised`
  but only `[&_a]:text-nav-content`, so the `<span>` crumbs (section triggers + current leaf)
  inherited the body default `text-content` — dark on the dark band. Added `text-nav-content`
  to the band `<header>` so plain crumbs inherit the readable nav colour; links keep their
  `[&_a]` override (to beat the global anchor colour). Guarded by a feature test asserting the
  standalone class is present.
- Found a pre-existing, **unused** `resources/views/components/breadcrumbs.blade.php` (array-
  shaped `items`, from the original component-library commit, no callers anywhere). Replaced
  it outright with the `Crumb`-based W3C implementation this spec requires — nothing else
  referenced the old shape.
- Feature-test a11y "exactly one `aria-current="page"`" must be scoped to the breadcrumb
  `<nav>`, not the whole page: the primary nav menu also marks its active link with
  `aria-current="page"`, so a page-wide count is 2. Only the `aria-label="Breadcrumb"` landmark
  is unique page-wide.
- `tests/Unit/BreadcrumbsTest.php` builds its `Request` by actually dispatching
  (`$this->actingAs($user)->get(route(...))`) and then reading `app('request')` back out,
  rather than hand-matching/binding a `Route` — gets real auth and implicit route-model
  binding for free instead of reimplementing it.

## Feedback & decisions (task 03)

- **Seam for the view-supplied trail**: `RevisionController::index()`/`compare()` build the
  `Crumb[]` tail directly (two small private helpers, `revisionsTrail()` +
  `revisionsLeaf()`) and pass it to the view as `breadcrumbTrail`, rather than adding a
  factory to `App\Support\Breadcrumbs` — keeps the central class untouched, as the task
  requires, and the tail is only ever built in these two call sites.
- **Heading and breadcrumb leaf share one computed string** (`$heading`, passed to both
  the view's `<x-heading>` and the trail's current `Crumb`) — one place decides the
  wording, so they can't drift apart.
- **`revisions.field` / `revisions.field-compare` need no changes**: both are pure
  redirects to `revisions.index`/`revisions.compare` (no view of their own), so the
  "~4 views" in the task scope are really 2.
- **"Back to editing" / "Back to history" relocated, not dropped** — same call as the
  dashboard's word count + "Edit Project" link (task 02 deviation): moved beside the page
  heading in a flex row, using `x-heading level="1"` rather than `x-page-heading` (no
  actions affordance), same as `projects/show.blade.php`.
