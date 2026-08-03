# 01 — `users.active_project_id`

**Depends on:** nothing.

## Scope

- Migration adding `active_project_id` to `users`: nullable, after `theme_slug`,
  `constrained('projects')->nullOnDelete()`; `down()` uses `dropConstrainedForeignId`.
- `User::activeProject(): BelongsTo`.

**Not in scope:** nothing writes the column yet (task 03) and nothing reads it (task 04). The
column being null for every existing user is the correct end state of this task.

## Key decisions

- **Copy `2026_07_04_000000_add_event_id_to_scenes_table.php`** — same nullable +
  `nullOnDelete()` + `dropConstrainedForeignId` shape, for the same "unassign, don't cascade"
  reason.
- `constrained('projects')` needs the explicit table name; Laravel infers `active_projects`.
- **Not added to `$fillable`.** Same rule as `Import::$user_id`.
- `DatabaseSeeder` is untouched — the seeded admin starts with no active project, which keeps the
  "Choose a project" state reachable in dev.

See `expanded/data-model.md`.

## Tests

New `tests/Feature/ActiveProjectTest.php` (later tasks extend it):

- Deleting the active project nulls the column.
- Deleting a *different* project leaves it.
- Deleting the user still succeeds — the users ⇄ projects FK cycle is the risk here, and a break
  shows up as a constraint violation, not a failed assertion.

No dedicated migration test: the FK's behaviour is what the above asserts.
