# 05 — Bare login lands on the active project

**Depends on:** 01. (Independent of 02–04, but ordered last: it is the only task that changes an
auth flow, and it reads best on top of a working feature.)

## Scope

- `AuthenticatedSessionController::store`'s final `redirect()->intended(...)` targets the active
  project's `projects.show` when the user has one, the dashboard otherwise.

**Not in scope:** logout, registration, password reset. `ProjectController::destroy` keeps
redirecting to the dashboard.

## Key decisions

- **`intended()` still wins.** A user bounced off a deep link goes to that link; this only changes
  the *bare* login case. Keep `absolute: false` on the dashboard fallback.
- **`projects.show`, not `projects.story.index`** — the same destination the picker uses. One
  "open the project" target, not two.
- Reversal of the expanded docs' original recommendation; see `expanded/open-questions.md` → Q4.

> [!NOTE]
> `/dashboard` carries `verified`, project routes carry only `auth`. Inert today (`User` does not
> implement `MustVerifyEmail`, so the middleware passes everyone) — but if verification is ever
> switched on, this redirect routes around a check the old destination had. Worth a comment on the
> line, not a guard.

## Tests

Add to `tests/Feature/Auth/AuthenticationTest.php` (or `ActiveProjectTest` if it reads better
beside the rest of the feature — pick one, don't split across both):

- Login with an active project redirects to that project's `projects.show`.
- Login with a null column still redirects to the dashboard.
- A request to a protected URL, then login, still lands on that URL — `intended()` is not
  clobbered.
