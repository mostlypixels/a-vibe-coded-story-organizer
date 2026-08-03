# Active project persistence — plan overview

The nav's project becomes a property of the account, not of the URL: the last project page
successfully loaded is stored on `users.active_project_id` and drives the nav everywhere,
including the dashboard, `/profile` and `/admin/*`.

## Execution order

| # | Task | Purpose |
|---|---|---|
| 01 | `active-project-column` | Migration + `User::activeProject()`. Deleting the project nulls it. |
| 02 | `route-project-support` | Extract the route→project walk out of `ProjectNavigation`. Pure refactor. |
| 03 | `track-active-project-middleware` | Write the column after any successful project page load. |
| 04 | `navigation-fallback` | Nav falls back to the stored project; `PageTitle` does not. |
| 05 | `login-redirect` | A bare login lands on the active project. |

The feature is invisible until 04. 01–03 are provable by database assertions alone.

## Binding decisions

Settled in `expanded/open-questions.md` (grilled 2026-08-03). Do not re-litigate:

- **Tracking is per-request, not per-`show()`.** A bookmark into any project page activates that
  project. Q3.
- **The write is gated on a 2xx response**, after `$next($request)` — that is what makes it
  post-authorization, and it is why the middleware needs no ownership check of its own. Q3/Q7.
- **`<title>` follows the URL, never the stored project.** Q1.
- **No "leave project" control.** Sticky until another project replaces it; the "Choose a
  project" state is for new accounts. Q2.
- **Bare login → the active project's `projects.show`**; `intended()` still wins. Q4.
- **The middleware registers on the `auth` group in `routes/web.php`**, not `bootstrap/app.php`. Q5.
- **The project menu on `/admin/*` and `/profile` is a goal**, not a side effect: one click back to
  the work after a settings detour. Q6.

## Invariants every task must preserve

- **Nothing here is user-submitted.** No new route, Form Request or policy — `active_project_id`
  stays out of `User::$fillable`, so no `update($request->validated())` can ever reach it.
- **A rejected request must not write.** Any change to the middleware keeps the 2xx gate; without
  it, a 403 would park another user's project name in the picker.
- **A page with no project in its URL never clears the stored value.** That is the persistence.
- **`ProjectNavigation` stays the single source of "which project / which section".** No
  `request()->routeIs()` in templates; the two menu components keep reading one object.
- **The route wins for display when there is one.** The stored project is a fallback, never an
  override.

## Accepted consequences

- One value per account, so two tabs in two projects share it; the drift shows only on pages with
  no project in the URL. `expanded/overview.md` → *Accepted consequence*.
- The picker's "All projects" link is navigation, not an exit — the nav still shows the project on
  the dashboard.
