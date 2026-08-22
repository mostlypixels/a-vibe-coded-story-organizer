# 09 — Onboarding controller, request, routes

## Scope

- Grow `OnboardingController` (today one `__invoke`) into named actions:
  - `show` (`GET /onboarding`) — the page; redirect to `projects.index` if the user already
    has a project (keep today's guard).
  - `store` (`POST /onboarding`) — validate, call the task-03 seed action for the acting
    user, redirect to `projects.show` with a hint flash (`status = onboarding-seeded`).
  - `installDemo` (`POST /onboarding/demo`) — call task 06's install path for the acting
    user, redirect to `projects.index`.
- `StoreOnboardingRequest`: `name` required string max 255 (reuse `StoreProjectRequest`'s
  rule shape), `genre` required, must be a `Genre` case.
- Routes: add the two POST routes beside the existing `onboarding` GET, behind `auth`.

Not in scope: the view markup (10); the banner render on the project home (11).

## Depends on

- 03 (seed action), 06 (demo path).

## Key decisions

- Skip posts to `store` with `genre = Blank` — one seed path, no separate action.
- The acting user is always `$request->user()`. Never read a user id from input.
- No policy needed (user seeds only for themselves), but keep the routes behind `auth`.

## Consult

- `expanded/architecture.md` → "Web flow", "Post-seed hint".

## Tests

- `GET /onboarding` shows the form; redirects when the user has a project.
- `POST /onboarding` (genre + name) creates the seeded project, redirects to `projects.show`,
  flashes the hint status.
- Skip (Blank) creates an empty project.
- `POST /onboarding/demo` installs the demo for the acting user only; a second user's counts
  are unchanged.
- Validation: missing name and unknown genre are rejected.
- Guest is redirected to login on every onboarding route.
