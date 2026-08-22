# Onboarding — testing

Plain PHPUnit, `RefreshDatabase`, factories, `actingAs()`, named routes.

## Seed action (feature test)

- Fantasy seed creates a project with `genre = Fantasy`, the expected attributes on the
  right entry types, tags, sample entries, and the act/chapter skeleton on the first book.
- Every seeded attribute value resolves at Start (`AttributeTimeline::valueAt(startEvent)`
  is non-null) — guards the leading-anchor invariant.
- Blank seed creates the project with no attributes, tags, or extra entries — but still has
  the book, main plotline, and bookends from `Project::created`.
- If a bundle seeds scenes, `scene_codex_entry` is populated after the seed (matcher ran).
- The seed runs in one transaction: force a failure mid-seed, assert no project row leaks.

## Web flow (feature test)

- `GET /onboarding` shows the genre picker and demo action; redirects to `projects.index`
  when the user already has a project.
- `POST /onboarding` with a genre + name creates the seeded project and redirects to
  `projects.show`; the hint status is flashed.
- Skip (Blank) creates an empty project and redirects to `projects.show`.
- `POST /onboarding/demo` installs Melusine for the acting user and redirects.
- Validation: missing name → 422/redirect back with errors; unknown genre → rejected.
- Auth: guest is redirected to login on every onboarding route.
- Isolation: the seed and demo install attach only to the acting user; a second user's
  project count is unchanged.

## Artisan commands (feature test)

- `app:seed-project --genre=fantasy --name=... --user=<email>` creates the seeded project
  for that user; asserts the same bundle result as the web path.
- Invalid `--genre` fails and lists the allowed genres.
- `app:install-demo --user=<email>` creates the three Melusine projects; a second run is a
  no-op (idempotent guards).

## Seeder regression

`tests/Feature/DatabaseSeederTest.php` is today a full demo-data regression suite: it runs
`db:seed` and asserts Melusine projects, their word counts, reference pivots, timeline
dates, and challenges (plus the `Lorem ipsum` project from `SecondUserSeeder`).

- The demo assertions move to a new `app:install-demo` test — they still cover the same
  Melusine content, just triggered by the command instead of `db:seed`.
- The remaining `db:seed` test asserts only the admin user and zero Melusine projects.
- Decide `SecondUserSeeder`'s fate: keep it in `db:seed`, or move it under the demo
  install too (see open-questions).
