# Breadcrumbs — Testing

New feature test `tests/Feature/BreadcrumbsTest.php`, plain PHPUnit + `RefreshDatabase` +
factories + `actingAs`, asserting on rendered HTML (`assertSee(..., false)` for the trail
labels and the `aria-label="Breadcrumb"` / `aria-current="page"` markers).

## Cases

- **Trail shape per section** (one representative route each):
  - `projects.codex.index` (type=character) → Dashboard, Codex, Characters; Characters is
    current (`aria-current="page"`), Dashboard is a link to `projects.show`.
  - `codex.edit` → leaf is `Edit character — <name>` (action + entry name); Characters
    becomes a link.
  - `projects.scenes.create` → Story › Scenes › New Scene; Scenes linked, leaf current.
  - `events.edit` → Timeline › Events › `Edit Event — <title>`.
  - `projects.revisions.index` → Tools › Revisions.
  - `revisions.index` / `revisions.compare` (field history, **no** `{project}` param) → still
    render `Dashboard › Tools › Revisions › <entity leaf>` via the view-supplied tail. Guards
    the resolvable-but-not-via-route-param exception.
  - `projects.search.index` → Dashboard › Search (no section crumb).
- **Section crumbs are not links**: assert Story/Timeline/Codex/Tools render as text with
  no `href` (they are dropdown labels).
- **Root**: `projects.show` renders a trail of just `Dashboard`, marked current, no link.
- **No band off-project**: `dashboard`, `profile.edit`, an `admin.*` page render **no**
  `aria-label="Breadcrumb"` landmark and still render their existing header slot.
- **Landmark + a11y**: exactly one `<nav aria-label="Breadcrumb">`; exactly one
  `aria-current="page"`; separators are `aria-hidden`.
- **Dynamic label is the model's**: rename the entity in the factory, assert the leaf label
  (the entity portion of `Edit … — <name>`) changes (guards against a hard-coded label).
- **Authorization unchanged**: a non-owner hitting an in-project route still gets 403 — the
  breadcrumb builder must not leak a bound model's label before authorization runs. Reuse
  the existing per-controller 403 tests; add one asserting the label is absent on the 403.

## Regression guard

- `BladeComponentCompilationTest` already compiles every component — the new
  `x-breadcrumbs` is picked up for free; confirm it passes.
- Converting ~30 header slots: no test should assert on the removed "Back to X" link text.
  Grep tests for `Back to` and update/remove stale assertions.
