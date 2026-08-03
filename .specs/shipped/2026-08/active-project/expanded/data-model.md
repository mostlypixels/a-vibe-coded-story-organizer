# Data model

## Migration

`database/migrations/2026_08_03_000000_add_active_project_id_to_users_table.php`

```php
$table->foreignId('active_project_id')
    ->nullable()
    ->after('theme_slug')
    ->constrained('projects')
    ->nullOnDelete();
```

`down()` uses `dropConstrainedForeignId('active_project_id')` — plain `dropColumn` leaves the
index behind on MySQL.

- **`nullOnDelete()` *is* the "cleared on delete" requirement.** No `deleting` hook on `Project`,
  no cleanup in `ProjectController::destroy`. A DB constraint cannot be bypassed by a cascade, a
  seeder, or a future bulk delete — the pattern the `Project`/`CodexMedia` hooks exist *despite*,
  because those clean up files the database cannot see. This is a column; the database owns it.
- **`constrained('projects')` needs the explicit table name** — Laravel would infer `active_projects`.
- **The users ⇄ projects FK cycle is safe.** `projects.user_id` cascades on user delete, and
  `User::booted()` already Eloquent-deletes the user's projects first, which nulls this column
  before the user row goes. Migration rollback order (reverse chronological) drops this column
  before `projects` is dropped.
- **`scenes.event_id` is the precedent** — `2026_07_04_000000_add_event_id_to_scenes_table.php` is
  the same shape (nullable + `constrained()->nullOnDelete()`, `dropConstrainedForeignId` in
  `down()`) for the same reason: deleting the target unassigns rather than cascades. Copy it.
  SQLite handles the rollback via Laravel's table-rebuild path
  (`SQLiteGrammar::getAlterCommands()` lists `dropForeign`), so there is nothing special to verify.

## `User`

- `activeProject(): BelongsTo` → `Project::class`.
- **Not added to `$fillable`.** The value is never submitted by a user; it is written only by
  `TrackActiveProject`. Same rule as `Import::$user_id` — keeping it unfillable means no future
  `$user->update($request->validated())` can ever set it.
- No cast, no accessor. `activeProject` returning `null` is the whole API.

## Seeding

`DatabaseSeeder` leaves `active_project_id` null. The admin user is created before the three
Melusine seeders run and `WithoutModelEvents` is in play; wiring a cross-seeder handoff to
pre-activate a project buys one saved click and couples four seeders. Dev's first page load after
`migrate:fresh --seed` shows the "Choose a project" state, which is the state hardest to reach
otherwise.

## Query impact

One extra row read per authenticated request, and only when the route resolves no project:
`ProjectNavigation` lazily hits `$user->activeProject`. `otherProjects()` already excludes
`$this->project`, so the active project stops being listed twice in the picker with no change
there.
