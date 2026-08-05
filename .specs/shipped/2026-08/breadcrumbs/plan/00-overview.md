# Breadcrumbs — plan overview

Replace the per-page header band (title + "Back to X") with a two-column band: left a
breadcrumb trail mirroring the nav hierarchy, right an empty reserved action slot. Trail is
derived centrally from the route, same discipline as `PageTitle` / `ProjectNavigation`.

## Execution order

1. **01 — value objects + components.** `Crumb`, `Breadcrumbs` builder, `x-breadcrumbs`,
   `x-page-heading`. Isolated + unit-tested; nothing wired, so no page changes yet.
2. **02 — atomic cutover.** Composer wiring + two-column band (with header-slot fallback) +
   convert every route-resolvable in-project page (remove header slot, add `x-page-heading`).
   Carries the feature/a11y tests. Forced atomic — see invariant below.
3. **03 — revisions history/compare exception.** The `{entity}`+`{id}` pages the central
   builder can't resolve; view supplies an explicit trail tail.
4. **04 — docs + changelog.**

## Binding decisions (settled in grill — do not re-litigate)

- **Central builder off `routeProject`** (not `project`), mirroring `PageTitle`: null →
  empty trail → band falls back to the page's `header` slot. This is what leaves dashboard,
  `/profile`, `/admin/*` untouched automatically.
- **Section crumbs** (Story/Timeline/Codex/Tools) are **plain text, no link** — they are
  dropdown triggers with no page.
- **Index route** → the sub-index item is the current leaf (no duplicate crumb).
- **Edit/create leaf is action-precise**: `New <Thing>`, `Edit <thing> — <model name>`
  (e.g. `Edit character — Mélusine`). Verb + `<thing>` are the builder's only translatable
  strings; the entity portion comes from the bound model.
- **Dashboard (`projects.show`)** renders a one-item band (`Dashboard`, current).
- **Fully central** — views pass nothing for labels. The **sole exception** is the revisions
  history/compare pages (task 03), which pass a trail tail.
- **Heading**: each converted page gets a shared `<x-page-heading>` above its content column
  (breadcrumbs are a `<nav>`, not a document heading).
- **Admin out of scope** — separate feature, own `AdminNavigation`.

## Invariants every task preserves

- **No headless page, ever.** The builder is route-driven and goes live atomically: the
  instant the composer + band land, every in-project route renders breadcrumbs and its old
  header-slot title is suppressed. So machinery-wiring and page conversion (header-slot
  removal + `x-page-heading`) must land together — task 02. Pages the builder yields empty
  for (revisions history/compare) keep their header slot until task 03; that fallback is what
  keeps them non-headless in between.
- **Authorization unchanged.** No new routes/policies. The builder must not surface a bound
  model's label before the controller's existing authorization runs — a non-owner's 403 must
  not leak an entity name.
- **One landmark, one current.** Exactly one `<nav aria-label="Breadcrumb">`, exactly one
  `aria-current="page"`; separators are `aria-hidden` decoration, never list items.
- **Labels from config/models, never literals** — app conventions (no magic strings).
