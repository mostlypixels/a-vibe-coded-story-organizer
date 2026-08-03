# 04 — Nav reads the stored project; the title does not

**Depends on:** 01, 02, 03. This is where the feature becomes visible.

## Scope

- `ProjectNavigation`: new `public readonly ?Project $routeProject` (what `resolveProject()`
  returns, unchanged meaning), and `$project` becomes `routeProject ?? $request->user()?->activeProject`.
- `AppServiceProvider`'s view composer builds `PageTitle` from `$navigation->routeProject`.
- `documentation/architecture.md`: update *Navigation active state* (the nav's project is now
  `route ?? account`, and `RouteProject` owns the walk), *Page title* (state that the title
  deliberately does **not** follow the stored project, and why), and *Project picker* (it renders
  outside projects now).

**Not in scope:** Blade changes. Both menus, the picker's two trigger states and the responsive
active-project row already read `$navigation->project` and start rendering on more pages by
themselves. The dashboard's `projects.edit` links stay as they are — with 03 in place they
activate the project anyway.

## Key decisions

- **The route wins when there is one.** The stored project is a fallback, never an override, so
  on a project page nothing changes.
- **The `*Active` section flags keep matching on the route only.** The dashboard therefore renders
  the menu with nothing highlighted — correct, because no section is open.
- **The `PageTitle` line is the trap.** Leave it reading `$navigation->project` and the dashboard,
  profile and every Configuration page silently retitle to `"<project> - imagoldfish"`. Q1.

See `expanded/architecture.md` → *Reading* and *`AppServiceProvider`*.

## Tests

Extend `ActiveProjectTest`:

- Dashboard with an active project: the response carries the project-menu hrefs
  (e.g. `route('projects.story.index', $project)`) and no `aria-current="page"` on any of them.
- Dashboard with a null column: "Choose a project", and no story link.
- Deleting the active project, then loading the dashboard: back to the "Choose a project" state.

Assert on hrefs and `aria-current`, never Tailwind classes — `NavigationTest`'s rule.

Existing tests to check:

| Test | Why |
|---|---|
| `PageTitleTest` | Add: dashboard with an active project still renders the bare app name. This is the regression a later "make the title match the nav" refactor would trip. |
| `NavigationTest` | Its no-project cases pass only because factoried users have a null column. Make one case explicit — set the column, assert the menu renders off-route — so the guarantee stops being accidental. |
| `EmptyStateTest` | Check for no-project-menu assumptions before assuming it is unaffected. |
