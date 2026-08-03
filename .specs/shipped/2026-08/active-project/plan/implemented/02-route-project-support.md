# 02 — Extract `App\Support\RouteProject`

**Depends on:** nothing (independent of 01).

## Scope

- New `App\Support\RouteProject` with `public static function resolve(Request $request): ?Project` —
  the body of `ProjectNavigation::resolveProject()`, moved verbatim.
- `ProjectNavigation::resolveProject()` becomes a call to it.
- Update the `ProjectNavigation` class docblock: its "adding a project-scoped section means
  touching this class and nothing else" now points at `RouteProject` for the route-parameter
  fallback half.

**Not in scope:** any behaviour change. This task is green when the existing suite is green.
The middleware that motivates the extraction is task 03.

## Key decisions

- **Extracted, not duplicated.** The middleware and the nav must agree on which project a URL
  belongs to; two copies of the `scene → chapter → act → project` walk drift the first time a
  route parameter is added — the failure `ProjectNavigation`'s own docblock already documents for
  the two menu copies.
- Static, no state. It answers one question from the request.

See `expanded/architecture.md` → *`App\Support\RouteProject`*.

## Tests

None new. `NavigationTest` already exercises every branch of the walk through real routes; if it
stays green the move is correct. Do not add a unit test that re-asserts what those cover.
