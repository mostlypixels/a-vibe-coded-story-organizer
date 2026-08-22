# 04 — Seed-project command

## Scope

- `App\Console\Commands\SeedProjectCommand` (`app:seed-project`):
  `--user=` (email or id), `--genre=blank`, `--name=`.
- Resolve the user (default: first user in local dev). Map `--genre` to a `Genre` case;
  reject an unknown value and list the allowed genres.
- Call the task-03 action. No seed logic in the command.

Not in scope: any demo/fixtures install (06, 07).

## Depends on

- 03.

## Key decisions

- Thin wrapper only. The action is the single source of seed behavior.
- The command runs with model events on (normal artisan), matching the action's needs.

## Consult

- `expanded/architecture.md` → "Artisan command".

## Tests

- `app:seed-project --genre=fantasy --name=… --user=<email>` produces the same bundle as
  the action for that user.
- Unknown `--genre` fails and names the allowed genres.
- Missing `--name` fails clearly.
