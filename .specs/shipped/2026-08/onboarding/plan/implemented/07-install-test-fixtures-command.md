# 07 — Install-test-fixtures command

## Scope

- `App\Console\Commands\InstallTestFixturesCommand` (`app:install-test-fixtures`).
- Runs, with model events **off**:
  - `SecondUserSeeder` (the non-owner writer, for 403 checks).
  - the demo (Melusine) — reuse task 06's path so it stays one behavior.
- Idempotent overall (each underlying seeder guards itself).

Not in scope: `db:seed` cleanup and Makefile (08). **Never** reference `LongNovelSeeder` —
its class is gitignored and absent in CI.

## Depends on

- 05, 06.

## Key decisions

- Fixtures = committed demo + committed second user. One command → a populated app for
  visual checks and the test suite.
- Keep it a superset of the demo so `make seed` needs only this one.

## Consult

- `expanded/architecture.md` → "Demo install command".
- `expanded/testing.md` → "Seeder regression".

## Tests

- `app:install-test-fixtures --user=<email>` creates the demo projects and the second
  `writer@example.com` user + project.
- Second run is a no-op.
