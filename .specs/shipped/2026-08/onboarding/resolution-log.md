# Onboarding — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

> [!IMPORTANT]
> An **exception log, not a work journal**. A task that went to plan gets no entry — the
> diff and the task file already record what was built. Bullets under the headings below,
> root cause first, no per-task sections.

## Feedback & decisions

- Genre is a stored label only in v1. No behavior driven by it. Later idea: check whether
  epub metadata read by reader apps could use it. Not now.
- Bundle content ships thin (placeholders); full per-genre content is a later pass, out of
  scope.
- Two install commands: `app:install-demo` (Melusine only, events off) and
  `app:install-test-fixtures` (second user + demo). `db:seed` = admin user only.
- `LongNovelSeeder` stays manual and separate. Never referenced from committed code: the
  class and its JSON payload are gitignored, and the payload holds the author's real,
  unpublished novel. Absent in CI and fresh clones.
- Makefile: `make seed` / `make fresh` chain `install-test-fixtures`; new `make demo` runs
  `install-demo`.
- Two seed modes kept apart: genre seed runs with model events on (relies on
  `Project::created`); demo seeders run with events off (they build their own book).
- Skip reuses the create path with `genre = Blank`. One page, no wizard.

## Deviations from the spec/plan

_None yet._

## Issues → resolutions

- Seeder run standalone dropped `user_id`. Root cause: `projects.user_id` is not in
  `Project::$fillable`; `db:seed` sets it only because the artisan seed command calls
  `Model::unguard()` around the run. A command or test that calls a Melusine seeder directly
  must wrap it in `Model::unguarded()` (and `Model::withoutEvents()` for the no-double-book
  invariant). Tasks 06/07 must do the same.
