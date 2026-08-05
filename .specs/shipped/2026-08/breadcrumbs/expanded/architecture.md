# Breadcrumbs — Architecture

## Where the logic lives

A new view-model `app/Support/Breadcrumbs.php`, built from the request exactly like
`ProjectNavigation` and `PageTitle`, and supplied by the **same view composer** in
`AppServiceProvider::boot()` (the one already on `['layouts.navigation', 'layouts.app']`).
Add `->with('breadcrumbs', new Breadcrumbs($navigation))`.

Reuse, don't duplicate: `Breadcrumbs` takes the already-constructed `ProjectNavigation`
and reads its `routeProject` and `*Active` flags rather than re-deriving "which section".
The active-section decision stays in one place.

### Root rule (mirror PageTitle exactly)

Build from `$navigation->routeProject`, **not** `->project`. Null → empty trail → no band.
This is the same reasoning documented on `PageTitle`: off-route pages (dashboard,
`/profile`, `/admin/*`) must not inherit the stored active project's breadcrumbs. Note this
in the class docblock and point at `PageTitle`.

## The value object

```
Breadcrumbs implements IteratorAggregate, Countable   // so the view can @foreach / count
  isEmpty(): bool
  // ordered Crumb[]: label, ?url (null = not a link), current (bool)
```

`Crumb` is a tiny readonly value object (`app/Support/Crumb.php`): `label`, `?url`,
`current`. Exactly one crumb has `current = true` (the last one). Ancestors carry a `url`;
the current crumb's `url` is null.

## Building the trail

The trail = **Dashboard** + **section chain** for the active section, with the last crumb
marked current. The section chain follows the primary menu (`project-menu.blade.php`):

| Active section | Section crumb (unlinked) | Sub-index crumb (linked) | Leaf (current) |
|---|---|---|---|
| Story | Story | Story Overview / Acts / Chapters / Scenes → its `*.index` | entity title on edit/create |
| Timeline | Timeline | Plotlines / Events → its `*.index` | entity title/name |
| Codex | Codex | `{type}` plural → `projects.codex.index`, or Attributes | entry name |
| Tools | Tools | Revisions → `projects.revisions.index` | — |
| Search | — (direct link, no dropdown) | — | Search |

Rules:
- **Section crumbs** (Story/Timeline/Codex/Tools) are `url = null` — dropdown triggers have
  no page, matching both the menu and the spec's example.
- **`*.index` routes**: the sub-index crumb IS the current leaf (no extra crumb, no dup).
- **`*.create` / `*.edit`**: sub-index crumb is linked; append a current leaf that names the
  **action** — `New <Thing>` for create, `Edit <thing> — <model title/name>` for edit (e.g.
  `Edit character — Mélusine`, `Edit Event — <title>`). The `<thing>` word and the edit/create
  verbs are the only translatable strings the builder owns; the entity portion comes from the
  bound model.
- **Codex sub-index label** comes from the active codex type (reuse
  `ProjectNavigation::codexTypeIsActive` / the resolved type); Attributes pages use the
  Attributes sub-index instead.
- **Search** has no dropdown, so its trail is just `Dashboard › Search`.

### The revisions exception (project resolvable, but not via a route param)

`projects.revisions.index` carries `{project}` and resolves normally →
`Dashboard › Tools › Revisions`. The per-field **history/compare** pages
(`revisions.index`, `revisions.compare`, `revisions.field`, `revisions.field-compare`) bind
`{entity}` (a slug string) + `{id}`, **not** a `{project}` model, so `RouteProject::resolve`
returns null and the central builder yields nothing.

These ~4 views are the **one documented exception** to fully-central: their controllers
already resolve the revisionable entity (hence its project + a label), so the view passes an
explicit trail tail to the band — `Revisions` (linked, `projects.revisions.index`) + a current
leaf naming the entity/field. Do **not** teach `RouteProject` to reconstruct a model from the
`{entity}` slug + `{id}`; it is heavier and only these pages need it. See open-questions.

### Admin paths — no change

All `admin.*` routes (prefix `admin`, name `admin.`) have no project binding, so
`RouteProject::resolve` returns null → no band → the page's own `header` slot renders as
today. This is automatic; admin needs no special-casing. Note the admin **Revisions** page
(`admin.revisions.edit`, the `RevisionSetting` retention form) is a different feature from the
project **Tools › Revisions** browser and keeps its `AdminNavigation` sidebar — out of scope.

### Leaf labels from bound models

The shallow child routes bind the entity (`{scene}`, `{event}`, `{codexEntry}`, …), so
`Breadcrumbs` reads it off the request the way `ProjectNavigation::resolveActiveCodexType`
already reads `$request->route('codexEntry')`. Map: scene→`title`, act→`title`,
chapter→`title`, event→`title`, plotline→`name`, codexEntry→`name`.

Keep this route→trail mapping as one readable `match`/method per section on `Breadcrumbs`,
not scattered. It parallels `ProjectNavigation`'s active-flag block.

## Routes / controllers / policies

None. No routes, migrations, models, or authorization change — breadcrumbs are derived
from the current route and its already-authorized bound models. Controllers are untouched.

## Migration of existing header slots

- `layouts/app.blade.php`: the header band becomes two-column. If `$breadcrumbs->isEmpty()`
  is false, render `<x-breadcrumbs :items="$breadcrumbs" />` on the left and the (empty)
  `$headerActions` slot on the right; otherwise fall back to the existing `$header` slot for
  non-project pages. See ui.md for markup.
- Remove the `<x-slot name="header">` (title + "Back to X") from the ~30 **in-project**
  views (story/acts/chapters/scenes/plotlines/events/codex/codex-attributes index+create+edit,
  search, and the revisions browser/history/compare pages). Breadcrumbs replace both the title
  and the back-link. The revisions history/compare views instead pass the explicit trail tail
  described above.
- Leave the header slot untouched on non-project views: dashboard, profile/*, projects
  create/edit, admin/* (admin has its own `AdminNavigation`; out of scope).
