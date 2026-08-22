# 06 — Install-demo command

## Scope

- `App\Console\Commands\InstallDemoCommand` (`app:install-demo`): `--user=` (email or id).
- Resolve the user. Run the three Melusine seeders for that user, with model events **off**
  (wrap in `WithoutModelEvents`, as `db:seed` does today).
- Idempotent: a second run is a no-op (the seeders guard by the user's projects).

Not in scope: the second user / test fixtures (07); `DatabaseSeeder` cleanup (08). No
`LongNovelSeeder`.

## Depends on

- 05 (seeders accept a user).

## Key decisions

- **Events off.** The Melusine seeders build their own book/plotline/bookends; with events
  on they would get a second book.
- This command is what the onboarding demo button calls (task 09) — for the acting user.

## Consult

- `expanded/architecture.md` → "Demo install command".

## Tests

- `app:install-demo --user=<email>` creates the three Melusine projects for that user, each
  with one book (not two).
- Second run is a no-op.
- Codex references and word counts are populated (seeders already handle this).
