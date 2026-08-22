# 08 — db:seed cleanup, Makefile, seeder tests

## Scope

- `DatabaseSeeder::run()`: keep the admin-user block. Remove the three Melusine calls and
  `SecondUserSeeder`. After this, `db:seed` = admin user only.
- Makefile:
  - `make seed` and `make fresh` run `db:seed` then `app:install-test-fixtures`.
  - add `make demo` → `app:install-demo`.
- `documentation/development/docker.md`: update the seed row(s) to match.
- Move the demo-data assertions out of `DatabaseSeederTest` into a command test (task 06/07
  coverage): Melusine content, word counts, reference pivots, timeline dates, challenges,
  and the second-user project. `DatabaseSeederTest` keeps only: admin user exists, zero
  Melusine projects.

Not in scope: onboarding web flow (09+).

## Depends on

- 06, 07.

## Key decisions

- This is the task that flips `db:seed` to demo-less. Do it after the commands exist so the
  demo path is never unavailable.
- Same coverage overall — the demo assertions move, they are not deleted.

## Consult

- `expanded/testing.md` → "Seeder regression".
- `tests/Feature/DatabaseSeederTest.php`, `Makefile`.

## Tests

- `db:seed` creates the admin user and no Melusine projects.
- The relocated demo assertions pass under the command test.
- `bash scripts/verify.sh` green.
