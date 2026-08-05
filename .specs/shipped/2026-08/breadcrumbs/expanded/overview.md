# Breadcrumbs — Overview

Replace the per-page header band (title + "Back to X" link) with a two-column bar:
left = a breadcrumb trail mirroring the nav hierarchy; right = reserved, empty for now.

## Goals

- Every in-project page shows a breadcrumb trail rooted at the project **Dashboard**,
  following the same section structure as the primary menu.
- The trail is derived centrally from the route — no page hand-builds its own crumbs
  (same discipline as `PageTitle` / `ProjectNavigation`).
- Accessible per the [W3C breadcrumb pattern](https://www.w3.org/TR/wai-aria-practices-1.1/#breadcrumb):
  a `<nav aria-label="Breadcrumb">` landmark wrapping an ordered list, last crumb
  `aria-current="page"`.
- Right column exists as an (empty) action slot so a later spec can drop buttons in
  without touching the layout.

## Non-goals

- No breadcrumbs for non-project pages (dashboard root, `/profile`, `/admin/*`,
  project create/edit). Those keep their existing `header` slot — see architecture.
- No action buttons in the right column (separate spec).
- No change to the primary nav menu itself.

## User stories

- As a writer deep in a codex entry, I see `Dashboard › Codex › Characters › Mélusine`
  and can jump back to the Characters list or the project in one click.
- As a keyboard/screen-reader user, I reach the trail as a labelled landmark and hear
  the current page announced as current.

## Acceptance criteria

- Trail shape matches the menu: `Dashboard` first, then the section (dropdown label,
  **not a link** — dropdown triggers have no page), then the section sub-index (linked),
  then the leaf (current page, not a link).
- On an index page the sub-index item **is** the current/leaf crumb (no duplicate).
- Ancestor crumbs are links; the current page is plain text with `aria-current="page"`.
- The band renders only when the route belongs to a project (`routeProject !== null`);
  otherwise the page's own `header` slot renders as today.
- Dynamic labels come from the route-bound model (`$scene->title`, `$codexEntry->name`,
  the active codex type's plural label, …), never hard-coded.
- On edit/create the leaf names the action precisely (`Edit character — Mélusine`,
  `New Scene`), not just the entity.

## Example trails

| Page (route) | Trail |
|---|---|
| `projects.show` | Dashboard |
| `codex.edit` (Character Mélusine) | Dashboard › Codex › Characters › Edit character — Mélusine |
| `projects.codex.index` (type=character) | Dashboard › Codex › Characters |
| `projects.scenes.create` | Dashboard › Story › Scenes › New Scene |
| `events.edit` | Dashboard › Timeline › Events › Edit Event — <title> |
| `projects.search.index` | Dashboard › Search |
| `projects.revisions.index` | Dashboard › Tools › Revisions |
