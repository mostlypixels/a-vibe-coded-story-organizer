# 01 — Export drops revisions

## Scope

- `app/Services/StaticSiteExporter.php` — delete `addRevisions()` and its 7 call sites; drop the
  `$includeRevisions` parameter from `export()`, `addManifest()`, `addStory()`, `addTimeline()`,
  `addCodex()` and every per-entity writer that only forwarded it; drop the `includes_revisions`
  manifest key. `use App\Models\Revision` and `use App\Support\AutosavableFields` become unused.
- `app/Http/Requests/ExportRequest.php` — drop the `include_revisions` rule and its comment.
- `app/Http/Controllers/ExportController.php` — drop the third `export()` argument.
- `resources/views/admin/data/export-project.blade.php` — delete the `include_revisions` checkbox
  block (~lines 58–71).

**Not** in scope: `DATA_VERSION` stays 2 (task 03), the importer still reads the now-absent key and
resolves it to `false` (task 02), `RevisionOrigin::Import` survives (task 04).

## Depends on

Nothing.

## Key decisions

- `$includeMedia` and the **Include images & files** toggle are untouched. Do not tidy them while
  threading the same signatures.
- A stray `include_revisions` in the POST body is silently ignored — no `prohibited` rule.
- The form is not restyled around its one remaining checkbox.

## Tests

Amend `tests/Feature/ExportTest.php`:

- Delete the *Revision history export* section (~1244–1370) and the `exportZipWithRevisions()` helper.
- The form assertion (~line 213): `assertDontSee('Include revision history')`, keeping the
  **Include images & files** assertion.
- **New** — with revisions in the DB, an export writes none: `assertNoZipEntryContains($zip, 'revisions/')`
  **and** `assertArrayNotHasKey('includes_revisions', $manifest)`.
- **New** — posting `include_revisions=1` still exports, and the archive has no `revisions/` entry.

## Consult

`expanded/architecture.md` → *1. Export* · `expanded/testing.md`.
