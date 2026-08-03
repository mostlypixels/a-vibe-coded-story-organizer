# Active project persistence — overview

Today "which project am I in?" is derived from the URL alone (`ProjectNavigation::resolveProject`).
Leave a project page — dashboard, profile, `/admin/*` — and the project menu vanishes. This makes
the nav a property of the *user*, not of the request: the last project entered is remembered on the
account and the project menu renders everywhere until another project replaces it.

## Goals

- `users.active_project_id`, nullable, cleared when that project is deleted.
- Loading any page of a project makes it the active one — including a bookmark into a project the
  user was not "in".
- The project menu and the picker's project name render on every authenticated page once a project
  is active.

- A bare login lands on the active project's home instead of the dashboard (Q4).

## Non-goals

- No "leave project" control — sticky until another project replaces it (Q2).
- No change to authorization: nothing new is user-submitted, so there is no new form, route,
  request or policy.

## Accepted consequence: one value per account

Per-account, not per-session — the source spec fixed this, and it matches `users.theme_slug`.
Two tabs open in two projects therefore share one stored value, so a Configuration detour from
tab 1 shows whichever project tab 2 loaded last. On any page that *has* a project in its URL the
route still wins, so the drift is confined to the dashboard, `/profile` and `/admin/*`.

## User stories

- Log in, land on the dashboard, and the nav already offers the project I was last working in.
- Duck into Configuration or my profile to change one setting, then get back to what I was writing
  in a single click — not dashboard → project → section.
- Delete the project I was in and get the neutral "Choose a project" bar back, not a menu of dead
  links.

## Acceptance criteria

| | |
|---|---|
| Visiting `projects.show`, `projects.acts.index`, `scenes.edit`, … | sets `active_project_id` to that project |
| Revisiting a page of the already-active project | issues no `UPDATE` |
| Another user's project page | 403, and `active_project_id` untouched |
| Dashboard / profile / admin, active set | project menu renders, no section highlighted |
| Dashboard, active `null` | picker reads "Choose a project", no project menu |
| Deleting the active project | column is `null`; nav falls back to the empty state |
| Deleting a different project | column unchanged |
| `<title>` on the dashboard with an active project | bare app name — the title still follows the *route*, not the account (Q1) |
| Bare login with an active project | lands on its `projects.show`; no active project → dashboard; an intended URL still wins |

## Conflicts with existing invariants

- **Authorization runs inside the controller, after middleware would normally write.** The
  middleware therefore writes *after* the response and only on 2xx — see `architecture.md`. Write
  on the way in and a 403'd request would still store the id, and the nav would then print another
  user's project name.
- **`PageTitle` currently reads `$navigation->project`.** That property changes meaning here.
  `AppServiceProvider`'s composer must switch to the new route-only property or every page in the
  app inherits a project prefix.
- **`NavigationTest` / `PageTitleTest` / `EmptyStateTest` assert the no-project state** using
  freshly factoried users. Those users now have `active_project_id === null` by default, so the
  assertions hold — but they become implicit. See `testing.md`.
