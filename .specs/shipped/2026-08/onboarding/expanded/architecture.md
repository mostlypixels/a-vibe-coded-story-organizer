# Onboarding — architecture

## Shared seed action

`app/Services/SeedsGenreBundle.php` (or `ProjectSeeder`) — one entry point both the web
flow and the artisan command call, so they never drift.

```
seed(User $user, Genre $genre, string $name): Project
```

- Creates the project (`$user->projects()->create(['name' => ..., 'genre' => ...])`). The
  `created` hook makes the book, main plotline, and bookends.
- Applies the bundle for `$genre`: attributes, tags, sample entries (with Start values via
  `AttributeTimeline`), and the act/chapter skeleton on `$project->books()->first()`.
- Blank genre creates the project and applies nothing.
- Runs `SceneReferenceMatcher::syncProject()` last if the bundle seeds scenes.
- Wrap the DB writes in a transaction (no disk I/O here, so it is safe).

## Web flow

Extend `OnboardingController` (currently a single `__invoke`). Split into named actions and
a small request:

| Route | Action | Purpose |
| --- | --- | --- |
| `GET /onboarding` | `show` | The form (redirect to `projects.index` if user has projects) |
| `POST /onboarding` | `store` | Validate, call the seed action, redirect to `projects.show` with a hint flash |
| `POST /onboarding/demo` | `installDemo` | Run the Melusine seeders for the current user, redirect to `projects.index` |

- `StoreOnboardingRequest`: `name` required string max 255 (reuse the `StoreProjectRequest`
  rule shape), `genre` required enum-in `Genre`.
- Skip: `store` with `genre = Blank` and a default/blank name, or a dedicated tiny action.
  Prefer reusing `store` with Blank to keep one seed path.
- Authorization: routes are already behind `auth`; a user only ever seeds for themselves
  (`$request->user()`), so no policy is needed. Do **not** accept a user id from input.

## Artisan command

`app/Console/Commands/SeedProjectCommand.php` — thin wrapper over the seed action.

```
php artisan app:seed-project {--user=} {--genre=blank} {--name=}
```

- `--user` resolves by email or id; defaults to the first user in local dev.
- `--genre` maps to a `Genre` case; invalid value fails with the allowed list.
- Calls the same `seed()` action. No seed logic in the command.

## Demo install command

Prefer a **separate** command over a flag — clearer, and it maps 1:1 to the onboarding
button:

```
php artisan app:install-demo {--user=}
```

- Calls the three Melusine seeders for the resolved user.
- `DatabaseSeeder` no longer calls them; `make seed` / docker call `db:seed` then
  `app:install-demo` when demo data is wanted (see open-questions).

## Post-seed hint

`store` flashes a status key (e.g. `with('status', 'onboarding-seeded')`). `projects.show`
renders the dismissible hint when that key is present. No new column.
