# 03 — `TrackActiveProject` middleware

**Depends on:** 01 (column), 02 (`RouteProject`).

## Scope

- `App\Http\Middleware\TrackActiveProject`.
- Registered on the existing auth group in `routes/web.php`:
  `Route::middleware(['auth', TrackActiveProject::class])->group(...)`.

**Not in scope:** reading the value. The nav still shows a project only on project URLs until
task 04, so this task is proved by database assertions.

## Key decisions

The middleware writes **after** `$next($request)`, and returns early unless all of:

1. the response is 2xx,
2. `RouteProject::resolve($request)` is non-null,
3. that project's id differs from `$user->active_project_id`.

- **Step 1 is the authorization check.** By the time a response exists, the controller's
  `authorize('view', …)` has run — a 403 or 404 never reaches the write, so no ownership
  comparison and no `Gate::allows` call is needed. Writing on the way *in* is the bug this
  ordering exists to prevent.
- **Step 2 is why the value persists.** A page with no project in its URL does nothing; it must
  never clear the column.
- **Step 3 keeps the steady state read-only** — one `UPDATE` on entering a project, not one per
  page. Non-GET requests inside a project redirect (302, not 2xx), so the following GET does the
  write; no method check needed.
- Not registered in `bootstrap/app.php`'s `web` group: guests and the public share/robots routes
  live outside `auth`. `/dashboard` sits outside the group too but resolves no project.

See `expanded/architecture.md` → *`App\Http\Middleware\TrackActiveProject`*.

## Tests

Extend `ActiveProjectTest`:

- `projects.show` sets the column.
- A shallow child route (`scenes.edit`) sets it too — proves the aggregate walk is in play, not
  just the `{project}` parameter.
- **The bookmark case:** with A active, a direct `scenes.edit` in B makes B active. The scenario
  the whole design exists for.
- Opening project B when A is active replaces the value.
- A route with no project (`profile.edit`) leaves an active project set.
- **Non-owner: 403 *and* the column stays null.** Asserting only the 403 passes against a version
  that writes on the way in — this is the test that catches it.
- Revisiting the active project issues no `update "users"` — `DB::listen`, as `NavigationTest`
  already does for query assertions.
