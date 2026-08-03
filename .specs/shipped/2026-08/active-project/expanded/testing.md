# Testing

## `tests/Feature/ActiveProjectTest.php` (new)

Plain PHPUnit, `RefreshDatabase`, factories, `actingAs`, `route()` — the `ProjectTest` shape.

**Tracking**

- `projects.show` sets `active_project_id`.
- A shallow child route (`scenes.edit`) sets it too — proves the middleware uses the same aggregate
  walk as the nav, not just the `{project}` parameter.
- **The bookmark case:** with A active, a direct `scenes.edit` in B makes B active. The scenario
  this design exists for; a `show()`-only implementation passes every other test here and fails
  this one.
- A page with no project in the route (`dashboard`, `profile.edit`) leaves an active project set —
  the persistence claim itself.
- **Non-owner: the route 403s *and* `active_project_id` stays null.** The one test that would
  otherwise be forgotten; asserting only the 403 passes against a version that writes on the way in.
- Revisiting the active project issues no `UPDATE` — wrap in `DB::listen` and assert no statement
  matching `update "users"`. `NavigationTest` already imports `DB` for query assertions.

**Rendering** (assert on hrefs / `aria-current`, never Tailwind classes — `NavigationTest`'s rule)

- Dashboard with an active project: response contains `route('projects.story.index', $project)`
  and the project's name; no `aria-current="page"` on any project-menu item.
- Dashboard with `active_project_id` null: contains "Choose a project", not the story link.

**Deletion**

- Deleting the active project nulls the column (`$user->refresh()`), and the follow-up dashboard
  request renders the "Choose a project" state.
- Deleting a *different* project leaves the column alone.
- Deleting the *user* (`ProfileTest`'s destroy path) still succeeds — the FK cycle is the risk;
  a failure here is a constraint violation, not an assertion failure.

## Existing tests to touch

| Test | Why |
|---|---|
| `NavigationTest` | Its no-project assertions pass only because factoried users have a null active project. Make one case explicit (`active_project_id` set → menu renders off-route) rather than leaving the guarantee accidental. |
| `PageTitleTest` | Add: dashboard with an active project renders the bare app name. This is the regression that a later "make the title follow the nav" refactor would trip. |
| `EmptyStateTest`, `ProjectTest` | Check for no-project-menu assumptions before assuming they are unaffected. |

## Not worth a test

- A dedicated migration test. The existing `*MigrationTest` files cover backfills and column
  rewrites; a nullable FK's behaviour is proven by the deletion cases above.
- The `RouteProject` extraction in isolation — `NavigationTest` already exercises every branch of
  the walk through real routes.
