# 05 — Seeders accept a user

## Scope

- `MelusineSeederEn/Fr/It`: let a caller set the target user, instead of `User::first()`.
  Add a settable target (e.g. a `forUser(User)` method or public property). Fall back to the
  current default only when no user is set, so `db:seed` still works until task 08.
- The idempotency guard keys off the target user's projects (already does — keep it correct
  for the passed user).
- `SecondUserSeeder`: it creates its own `writer@example.com` user. Leave that, but make sure
  it can run standalone from a command (it already does).

Not in scope: the commands themselves (06, 07). Do not touch `DatabaseSeeder` yet (task 08).
Do not reference `LongNovelSeeder`.

## Depends on

- None (independent; do before 06/07).

## Key decisions

- Keep `db:seed` green through this task: the no-target fallback must preserve today's
  behavior. `DatabaseSeederTest` must still pass after this task.
- The three Melusine seeders share the pattern — apply the same change to each.

## Consult

- `expanded/data-model.md` → "Demo install".
- `database/seeders/MelusineSeederEn.php`, `SecondUserSeeder.php`.

## Tests

- A Melusine seeder run for a specific user attaches the project to that user.
- Re-running for the same user is a no-op (idempotent guard).
- Existing `DatabaseSeederTest` still passes.
