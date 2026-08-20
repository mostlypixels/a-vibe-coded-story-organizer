# 01 — Table and model

## Scope

- Migration creating `challenges` (columns and index: `expanded/data-model.md` → *New table*).
- `App\Models\Challenge` — casts, `$fillable`, `belongsTo(Project::class)`.
- `Project::challenges(): HasMany`, ordered `starts_on` descending.
- `App\Enums\ChallengeRecurrence` (`None`, `Monthly`) and `App\Enums\ChallengeState`
  (`Upcoming`, `Running`, `Finished`), both backed strings.
- `ChallengeFactory`, with states for a fixed and a monthly challenge.

**Not** in this task: any window or par arithmetic (02, 03), routes or forms (04),
export (07), seeding (08).

## Depends on

Nothing.

## Key decisions

- `ends_on` is nullable: required for `None`, optional stop date for `Monthly`. Enforced in
  the Form Requests (04), not the schema.
- No progress columns, no `HasRevisions`, no `user_id`, no unique key.
- `index(project_id, starts_on)` only.
- `ChallengeState` holds no logic here; 02 decides which state a challenge is in.

## Consult

`expanded/data-model.md`.

## Tests

- `tests/Feature/CreateChallengesMigrationTest.php` — columns, nullable `ends_on`, the index,
  and the cascade when a project is deleted. Follow `CreateWordCountSnapshotsMigrationTest`.
- Factory smoke coverage inside that test is enough; no model test of its own.
