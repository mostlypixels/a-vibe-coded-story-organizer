# Nav consistency — testing

The navigation renders on every authenticated page, so this is testable through any existing
authed route. Follow the house style (`tests/Feature/*Test.php`): plain PHPUnit,
`RefreshDatabase`, factories, `actingAs`, `route()` helper.

## Where to put it

No `NavigationTest.php` exists yet. Add `tests/Feature/NavigationTest.php`. Keep it focused on
the active-highlighting behavior; do not re-test routing/auth already covered elsewhere.

## What to assert

The active marker is `aria-current="page"` on the active link (plus the active class). Assert on
the string, not the exact Tailwind classes (classes are cosmetic and churn):

1. **Active item is marked on a Story page.**
   Visit `route('projects.scenes.index', $project)` as the owner; assert the response HTML
   contains an `aria-current="page"` anchor whose text/href is the **Scenes** link
   (`route('projects.scenes.index', $project)`). `assertSee(..., false)` on the href, or a
   small regex, is sufficient.

2. **Non-active sibling is not marked.**
   On that same Scenes page, assert the **Acts** link
   (`route('projects.acts.index', $project)`) is present but is **not** the `aria-current`
   anchor. (Guards against "everything highlights".)

3. **A child route still highlights its section.**
   Visit an act/chapter/scene *edit* page (e.g. `route('scenes.edit', $scene)` — matched by
   `scenes.*`) and assert the **Scenes** dropdown item is `aria-current="page"`. This exercises
   the `|| request()->routeIs('scenes.*')` half of the matcher.

4. **(If Q1 expands to Codex/Timeline)** one parity case: visit
   `route('projects.codex.index', [$project, 'characters'])` and assert the **Characters**
   dropdown item is marked, confirming the enum-aware matcher (`route('type') === 'characters'`)
   works in the desktop dropdown.

5. **(If Q2 taken — trigger highlighting)** assert the **Story** trigger carries its active class
   / marker on a Story page and not on, say, a Codex page.

## Edge cases to keep in mind

- **Story Overview vs Scenes:** `projects.story.*` and `projects.scenes.*` are distinct route
  namespaces, so only one should match on a given page — assert both directions to catch an
  over-broad `routeIs('projects.story*')`-style glob.
- **Codex `attributes` vs types:** on the Attributes page, no *type* item should be marked and
  Attributes should be — a regression guard if Codex is in scope.
- The nav only renders its section links when a `$project` is resolvable from the route
  (`navigation.blade.php:15-22`); all the routes above resolve one, so the `@if` is satisfied.

## Not needed

- No authorization test here — highlighting is a view concern with no new endpoint. Owner-only
  access to these pages is already covered by the resource tests.
- No factory/model changes.
