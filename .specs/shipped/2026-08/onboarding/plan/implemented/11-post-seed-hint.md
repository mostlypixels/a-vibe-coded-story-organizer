# 11 — Post-seed hint

## Scope

- On `projects.show`, render a dismissible banner when the `onboarding-seeded` status is
  flashed (set by task 09's `store`).
- Copy is final in `spec.md` → "After the seed (hint on the project home)". Wrap in `__()`.
- Dismiss is client-side only (the flash shows once; no persistence).

Not in scope: setting the flash (done in 09).

## Depends on

- 09.

## Key decisions

- Reuse the existing flash/alert component the app already uses for `status` messages.
- No new column, no server state — a one-time flash.

## Consult

- `expanded/ui.md` → "Post-seed hint".
- `resources/views/projects/show.blade.php`.

## Tests

- After `POST /onboarding` (seeded), `projects.show` shows the hint copy once.
- A normal visit to `projects.show` (no flash) does not show it.
