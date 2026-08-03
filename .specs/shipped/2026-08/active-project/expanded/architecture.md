# Architecture

Three pieces: a resolver extracted from `ProjectNavigation`, a middleware that writes after a
successful response, and a one-line fallback in `ProjectNavigation` that reads. No new route,
controller, request or policy.

**The rule, stated once:** the active project is the last project page the user *successfully*
loaded. Stored value and displayed value are therefore always the same thing.

## `App\Support\RouteProject`

Lift `ProjectNavigation::resolveProject()` verbatim into a static resolver:

```php
public static function resolve(Request $request): ?Project
```

The middleware and `ProjectNavigation` must agree on which project a URL belongs to; two copies of
the aggregate walk (`scene → chapter → act → project`) would drift the first time a route parameter
is added — the exact failure `ProjectNavigation`'s docblock already warns about for the two menus.
`resolveProject()` becomes a call to it.

> That docblock's "adding a project-scoped section means touching this class and nothing else" now
> points at `RouteProject` for the parameter-fallback half. Update it.

## `App\Http\Middleware\TrackActiveProject`

Registered on the existing auth group in `routes/web.php`:
`Route::middleware(['auth', TrackActiveProject::class])->group(...)`. Not in `bootstrap/app.php`'s
`web` group — guests have nothing to track, and the public share/robots routes deliberately live
outside `auth`.

It writes **on the way out**, not on the way in:

```php
$response = $next($request);

// … then, only if $response->isSuccessful()
```

1. Not 2xx → return. **This is the authorization check.** The controller's
   `$this->authorize('view', …)` has already run by the time the response exists, so a 403 or 404
   never reaches the write and a foreign project id can never be stored. No ownership comparison,
   no `Gate::allows` — the status code is the answer, and it stays correct if the policy changes.
2. `RouteProject::resolve($request)` is null (dashboard, profile, admin) → return. **An unrelated
   page never clears the active project** — that is what makes it persist.
3. Same id as `$user->active_project_id` → return. An `UPDATE` fires on entering a project, not on
   every page inside it.
4. Otherwise assign and `save()`.

A `PATCH`/`DELETE` inside a project redirects (302), so writes come from the GET that follows.
Harmless either way — step 3 makes the second one a no-op.

> [!NOTE]
> Writing in the controller (`ProjectController::show` only) was the simpler first draft and was
> rejected: a bookmark into `/scenes/{scene}/edit` in project B would leave A active, and the nav
> would silently revert to A the next time the user opened Configuration or the dashboard. See Q3.

**Write-on-GET is deliberate.** "Last project page loaded" cannot be derived from anything else,
and step 3 keeps the steady state read-only.

## Reading: `App\Support\ProjectNavigation`

- New `public readonly ?Project $routeProject` — what `resolveProject()` returns today, unchanged.
- `$this->project = $this->routeProject ?? $request->user()?->activeProject;`

Everything downstream follows for free — `hasProject()`, the picker trigger, `otherProjects()`'s
`whereKeyNot`, and both menu components already read `$this->project`. The `*Active` flags keep
matching on the route only, so the dashboard renders the menu with nothing highlighted, which is
correct: no section is open.

The fallback only fires on pages with no project in the URL. On a project page the route and the
stored value name the same project anyway — the middleware just wrote it.

## `AppServiceProvider`

The composer must build the title from the route project:

```php
->with('pageTitle', new PageTitle($navigation->routeProject));
```

Miss this and `$navigation->project` silently retitles the dashboard, profile and every
Configuration page `"Melusine - imagoldfish"`. The title answers *what is on this page*; the nav
answers *what am I working on*. They were the same question until this feature and are not any
more (Q1).

## Login redirect

`AuthenticatedSessionController::store` currently ends `redirect()->intended(route('dashboard',
absolute: false))`. It becomes: the active project's `projects.show` when there is one, the
dashboard otherwise — `intended()` still wins, so a user bounced off a deep link goes there.

`projects.show` (not `projects.story.index`) because it is where the picker sends you; one
"open the project" destination, not two.

> [!NOTE]
> `/dashboard` carries `verified`; project routes carry only `auth`. Inert today — `User` does
> not implement `MustVerifyEmail`, so the middleware passes everyone — but if verification is
> ever switched on, this redirect routes around a check the old destination had.

## UI

Markup changes: none. `layouts/navigation.blade.php`'s `@if ($navigation->hasProject())` guards on
both menus, the picker's two trigger states, and the responsive active-project row all read
`$navigation->project` already and start rendering on more pages by themselves.

Visible consequences to accept deliberately:

- The project menu appears above the dashboard, `/profile` and `/admin/*` (Q6). "All projects"
  stays a plain link — navigating away is not leaving the project (Q2).
- On the dashboard the picker names the active project and the desktop panel omits it; the table
  below is the full list, so nothing is unreachable.

Optional, not required by this feature: the dashboard points its cover, name and grid card at
`projects.edit` — the settings form. `projects.show` is the better target for "open this project",
but with the middleware in place either one activates it, so this is polish and can be dropped to
keep the diff tight.

## Docs to update

- `documentation/architecture.md` → *Navigation active state*: the nav's project is now
  `route ?? account`, and `RouteProject` is where the walk lives.
- Same file → *Page title*: state that the title deliberately does **not** follow the active
  project, with the reason. This is the trap for the next person.
- *Project picker*: the picker renders outside projects now.
