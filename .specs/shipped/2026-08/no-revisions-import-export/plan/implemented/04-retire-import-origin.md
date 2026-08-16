# 04 — Retire the `import` origin

With no producer left, `RevisionOrigin::Import` is unreachable state and
`RevisionPurger::CATEGORY_IMPORTED` is a purge category that can never match.

## Scope

| File | Change |
|---|---|
| `app/Enums/RevisionOrigin.php` | Remove `case Import`, its `label()` arm, and the docblock mention. |
| `app/Services/RevisionPurger.php` | Remove `CATEGORY_IMPORTED`, its `CATEGORIES` entry, its `match` arm. Reword the class docblock's "imported revisions and a two-year `manual` history" rationale — the release valve now exists for the `manual`/`labeled` ratchet alone. |
| `app/Console/Commands/PurgeRevisions.php` | Drop `imported` from the `--category` option description. The unknown-category error already reads `RevisionPurger::CATEGORIES`. |
| `resources/views/admin/revisions/edit.blade.php` | Drop the `CATEGORY_IMPORTED => __('Imported')` label and "and imported" from the panel's help text. |
| `app/Support/SavePoint.php` | Drop `RevisionOrigin::Import` from `ORIGIN_PRECEDENCE`. |
| `resources/views/components/revision-origin-badge.blade.php` | Drop the `Import => 'accent'` arm. |
| new migration | `DB::table('revisions')->where('origin', 'import')->delete();` |

`RevisionSettingController` needs **no** code change — it loops `RevisionPurger::CATEGORIES` for
both the storage panel's rows and the purge form's options, so it follows automatically. Its panel
now renders three rows.

## Depends on

02 (the importer is the last producer of `origin: import`).

## Key decisions

- **Delete the rows, never relabel to `manual`.** They are another install's edit trail; a "Saved"
  badge would claim a save that never happened here. Pre-V1, the only data is the Melusine seed —
  `documentation/revisions.md` → *"Why is my history empty?"* set the precedent.
- **`down()` is a no-op**, with a docblock saying so. Deleted history is not recoverable, and a
  lying stub is worse than an honest empty method.
- **No tombstone case.** Keeping an enum case with no producer means every future `match` carries a
  dead arm. Delete it and let the compiler find them.
- The `revisions.origin` column is a plain string cast to the enum — no schema change, but a
  surviving `'import'` row throws on hydration, which is why the migration is not optional.

## Tests

- **New** `DropImportedRevisionsMigrationTest` — follow `BackfillBaselineRevisionsMigrationTest`'s
  shape. Insert a raw `origin = 'import'` row via the query builder (the enum case is gone, so the
  model cannot create one), run the migration, assert it is gone and that `manual` / `automatic` /
  `baseline` / `revert` siblings survive.
- `tests/Feature/RevisionDataModelTest.php` — `assertCount(5, RevisionOrigin::cases())` → **4**;
  drop `Import` from the origin loop. This test is the tripwire that the enum and the DB agree —
  update it, don't delete it.
- `tests/Feature/RevisionRetentionAndPurgeTest.php` — drop the `imported` fixture row and
  `test_purger_removes_exactly_the_imported_category`; the three surviving "purger removes exactly
  X" tests lose their `assertModelExists($rows['imported'])` line. **New**: `purge('imported')`
  throws `InvalidArgumentException`.
- `tests/Feature/AdminRevisionsPageTest.php` — same fixture removal; the panel renders three
  category rows.
- `tests/Unit/Services/RevisionHistoryTest.php` (~244) — iterates `RevisionOrigin::cases()`; verify
  it hard-codes no count.

> [!WARNING]
> `Revision::prunable()`'s guard tests must pass **unchanged**. If one needs editing, something
> wider moved than this task — stop and say so in the resolution log.

## Consult

`expanded/architecture.md` → *4. The `import` origin* and *Data model — the migration* ·
`documentation/revisions.md` → *Prune vs purge*.
