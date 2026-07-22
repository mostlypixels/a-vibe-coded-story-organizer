# Autosave With Revisions — resolution log

The running record of feedback/decisions, deviations from the spec/plan, and
issues → resolutions found while implementing and verifying this feature. The
`plan-implementer` agent appends here per task; `ship-plan` consolidates it. Read it
before extending the feature.

## Feedback & decisions

_None yet._

## Deviations from the spec/plan

* Task 1: added `RevisionFactory` (not mentioned in the task file) so the
  required prunable() tests could seed rows the way this codebase's other
  model tests do (factories, not raw `Revision::create()`). Defaults to
  revisioning a fresh `Project`'s `description` field; tests override
  `revisionable_type`/`revisionable_id`/`project_id`/`field` as needed.
* Migration filename uses today's date (`2026_07_22_000000_create_revisions_table.php`)
  rather than the `2026_07_XX` placeholder in `data-model.md`, following this
  repo's existing migration-naming convention (sequential dated files).
* Task 2: migration filename likewise dated
  `2026_07_22_000001_widen_long_text_columns_to_long_text.php` (sequenced after
  task 1's same-day migration) rather than the `2026_07_XX_000001` placeholder
  in `data-model.md`.
* Task 2: `expanded/data-model.md`'s prose says widening `text()` → `longText()`
  "requires `doctrine/dbal`", but `00-overview.md`'s binding decision (confirmed
  during grilling) says the opposite and takes precedence. Confirmed again here:
  `composer.json`/`vendor/doctrine` has no `dbal` package, and
  `Schema::table(...)->longText('x')->nullable()->change()` ran cleanly against
  sqlite (the test DB) with no missing-dependency error — Laravel 13's schema
  builder performs the type change natively. No package was added.

## Issues → resolutions

* `Revision::prunable()` cannot be queried as `Revision::query()->prunable()`
  — `prunable()` is an **instance** method that `Illuminate\Database\Eloquent\
  MassPrunable` calls internally (via the scheduled `model:prune` command), not
  a local query-builder macro. Tests must call it as `(new Revision())
  ->prunable()` to get the `Builder` it returns. `data-model.md`'s code sample
  doesn't show test usage, so this only surfaced when writing task 1's tests —
  future tasks that need to run the prunable query directly (e.g. task 12's
  purge preview) should do the same.
* Laravel Pint's `fully_qualified_strict_types` fixer rewrote the `Revision`
  docblock's `{@see \App\Services\RevisionRecorder}` reference into a real
  `use App\Services\RevisionRecorder;` import — a class that doesn't exist
  until task 4. It doesn't break anything (PHP never resolves an unused `use`
  import), but it reads as a phantom dependency, so the docblock was reworded
  to reference `App\Services\RevisionRecorder` as plain text instead of a
  `@see` tag. Worth remembering for any other task that `@see`s a
  not-yet-built class in a docblock.
* Task 3: same Pint `fully_qualified_strict_types` fixer behavior recurred —
  `{@see \App\Support\AutosavableFields}` docblock references in both
  `FieldKind` and `HasRevisions` were rewritten into real `use
  App\Support\AutosavableFields;` imports (harmless, since `AutosavableFields`
  does exist by task 3, but Pint also silently dropped the `\` and shortened
  the `@see` tag body to the bare class name — left as Pint produced it, run
  again with `composer lint -- --test` to confirm idempotent/clean). No code
  change needed, just noting the pattern for any later task's `@see` tags.
* Task 3: PHPUnit's `@dataProvider` docblock annotation (as shown in some
  older Laravel docs/spec prose) is not supported by this project's PHPUnit
  version — it must be the `#[DataProvider('methodName')]` attribute
  (`PHPUnit\Framework\Attributes\DataProvider`), otherwise the test errors
  with `ArgumentCountError` (0 args passed, N expected) since the annotation
  is silently ignored. Confirmed via `AutosavableFieldsAndHasRevisionsTest`'s
  14-field-table data provider.
* Task 4: `architecture.md`'s `RevisionRecorder` sketch calls
  `AutosavableFields::windowSeconds(...)` with an elided argument list, but
  the real method (task 3) takes a URL *slug*, not a model class —
  `RevisionRecorder` only ever has a `Model $entity`. Added
  `AutosavableFields::slugFor(string $modelClass): string` (a small reverse
  lookup over `REGISTRY`) rather than duplicating the "Model.field" config-key
  derivation inside `RevisionRecorder` — keeps the slug/config lookup logic
  in exactly one place, per CLAUDE.md's "Configuration should be kept in a
  single place" rule. Not mentioned in the task file; a necessary extension
  of task 3's registry, not a redesign of it.
* Task 5: `down()` was written to delete `origin: baseline` revisions rather
  than being a no-op — the task file left this as an implementation choice to
  document. Chosen because leaving baseline rows behind on rollback would make
  a subsequent `up()` diverge from a fresh install's history: `ensureBaseline()`'s
  idempotent no-op check would then skip rows a fresh install would still seed.
  Safe either way `ensureBaseline()` seeded them (this migration or the live
  write path), since both routes produce identical rows.
* Task 5: migration filename uses `2026_07_22_000002_backfill_baseline_revisions.php`
  (dated/sequenced after tasks 1 and 2's same-day migrations), matching this
  repo's convention, rather than the `2026_07_XX_000002` placeholder in
  `data-model.md`.

## Issues → resolutions (continued)

* Task 5: tests can't rely on `RefreshDatabase`'s own migration run to exercise
  the backfill, since that run happens before any factory rows exist (nothing
  to backfill yet). Every test in `BackfillBaselineRevisionsMigrationTest`
  seeds rows first, then re-runs the migration's `up()` directly via
  `include database_path('migrations/...php')` — the standard pattern for
  testing a data migration in isolation, since Artisan's migration table
  would otherwise consider it already run.
* Task 5: PHP does not allow `{$entity::class}` inside a double-quoted string
  (parse error: "unexpected token \"}\"") — `::class` after `::` isn't valid
  inside `{...}` interpolation. Had to compute `$entityClass = $entity::class;`
  as a separate statement before interpolating it into assertion failure
  messages.
