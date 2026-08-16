# Testing

Mostly subtraction. The tests that must be *written* are the ones proving the old behaviour is
gone rather than merely untested.

## New

| Test | Where | Asserts |
|---|---|---|
| Export writes no revision artifacts | `ExportTest` | With revisions in the DB, `assertNoZipEntryContains($zip, 'revisions/')` **and** `assertArrayNotHasKey('includes_revisions', $manifest)`. Replaces the deleted toggle-off test. |
| The export form offers one toggle | `ExportTest` (line ~213) | `assertDontSee('Include revision history')`, keep the `Include images & files` assertion. |
| `include_revisions=1` is inert | `ExportTest` | Posting the old field still exports; the archive has no `revisions/` entry. Unknown input must be ignored, not 422 — old bookmarks and scripts exist. |
| A v3 archive with sidecars is rejected | `ArchiveValidatorTest` | A hand-built v3 zip carrying `.../revisions/contents.json` fails check 3 (disallowed path). |
| v1 and v2 are rejected | `ArchiveValidatorTest` | One case each, alongside the existing `version => 999` case. |
| A sidecar is never replayed | `ProjectGraphImporterTest` | v3 fixture with `includes_revisions: true` **and** `revisions/contents.json` written directly into the extraction dir (bypassing the gate): import succeeds, `Revision::query()->count() === 0`. Catches a half-removed replay. |
| Round trip creates no history | `ImportRoundTripTest` | After the existing export → import, assert zero `Revision` rows on the new project. |
| The migration drops `import` rows | new `DropImportedRevisionsMigrationTest` | Follow `BackfillBaselineRevisionsMigrationTest`'s shape: insert a raw `origin = 'import'` row via the query builder (the enum case is gone, so the model can't create one), run the migration, assert it is gone and that `manual`/`automatic`/`baseline`/`revert` siblings survive. |
| The purge categories are three | `RevisionRetentionAndPurgeTest` | `purge('imported')` throws `InvalidArgumentException`; `CATEGORIES` has no `imported`. |

## Amend

| Test | Change |
|---|---|
| `ExportTest` | Delete the whole *Revision history export* section (~1244–1370) and the `exportZipWithRevisions()` helper. |
| `ProjectGraphImporterTest` | Delete the *Revision history import* section (~364–520); keep the `writeManifest(includesRevisions:)` helper only if the new inert-sidecar test uses it, otherwise drop the parameter. |
| `ProjectImporterTest` | Drop the `RevisionOrigin::Import` assertions. |
| `RevisionDataModelTest` | `assertCount(5, RevisionOrigin::cases())` → **4**; drop `Import` from the origin loop. This test is the tripwire that the enum and the DB agree — update it, don't delete it. |
| `RevisionRetentionAndPurgeTest` | Drop the `imported` fixture row and `test_purger_removes_exactly_the_imported_category`; the three surviving "purger removes exactly X" tests lose their `assertModelExists($rows['imported'])` line. |
| `AdminRevisionsPageTest` | Same fixture removal; the panel now renders three category rows. |
| `RevisionHistoryTest` (~244) | Iterates `RevisionOrigin::cases()` — verify it doesn't hard-code a count. |

## Edge cases to keep in mind

- **Prune/purge safety is untouched.** `Revision::prunable()` never mentioned the origin except by
  matching `automatic`; removing a *different* case must not perturb it. Its guard tests
  (`test_the_prune_keeps_the_newest_revision_even_when_it_was_inserted_first`) must pass unchanged
  — if one needs editing, something wider moved than this feature.
- **Save-point folding.** `SavePoint::ORIGIN_PRECEDENCE` shortens by one; a group of mixed
  `manual`/`automatic` rows must still resolve to `Manual`.
- **Five fixtures hardcode `'version' => 1` or `2`** — `ImportTest` (×2), `ArchiveValidatorTest`,
  `ProjectGraphImporterTest`, `ProjectImporterTest`. All move to 3; missing one turns a passing
  test into a rejection.
- Run `bash scripts/verify.sh`; the changed surface is PHP + one Blade toggle, so no JS test moves.
