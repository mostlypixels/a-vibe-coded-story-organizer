# 03 — Revisions history/compare exception

The per-field history/compare pages bind `{entity}` (slug) + `{id}`, not `{project}`, so
`RouteProject::resolve` returns null and the central builder yields empty. Convert these pages
by having the view supply an explicit trail tail. This is the **one documented exception** to
fully-central; keep it contained here.

## Scope

- Views: `revisions/index.blade.php` (history), `revisions/compare.blade.php`, plus the
  `revisions.field` / `revisions.field-compare` views if separate. Remove their header slots,
  render the two-column band with a **view-supplied** trail: `Dashboard` (link) ›
  `Tools` (text) › `Revisions` (link → `projects.revisions.index`) › current leaf, and add an
  `<x-page-heading>`.
- The controller already resolves the revisionable entity (hence its project + a label) — pass
  what the tail needs from there; do **not** teach `RouteProject` to rebuild a model from the
  slug.
- Leaf wording (grill-decided): `<Entity> "<title>" — History`, `… — <field> history`,
  `… — Compare`, `… — <field> compare`.
- Provide a small, explicit way for a view to render the band with a supplied trail (e.g. build
  a `Breadcrumbs`/`Crumb[]` in the controller or a view helper) that reuses `x-breadcrumbs` —
  same component, same a11y markup as task 01. Decide the exact seam during implementation;
  keep it to these ~4 views.

## Explicitly not in scope

- `projects.revisions.index` (the browser) — already converted in task 02 (it has `{project}`).
- Any change to the central builder's route-driven path.

## Depends on

- 01 (components), 02 (band + fallback in the layout).

## Key decisions (from grill / spec)

- Contain the special case to these views; the central builder stays untouched.
- Entity + title named in the leaf (not a bare "History"), for orientation deep in a record's
  history. See `expanded/architecture.md → The revisions exception`.

## Tests

- Extend `tests/Feature/BreadcrumbsTest.php`: `revisions.index` and `revisions.compare` render
  `Dashboard › Tools › Revisions › <entity leaf>` — Revisions is a link, Tools is text, exactly
  one `aria-current="page"`. Guards the resolvable-but-not-via-route-param path.
- Owner + 403 non-owner on a history route; label absent on the 403.
