# 02 — Import drops the replay

## Scope

`app/Services/Import/ProjectGraphImporter.php` only:

- Delete `importRevisions()`, `includesRevisions()`, `summarizeImported()`.
- Drop every `$includeRevisions` parameter and `use (...)` capture down the four phase methods and
  their helpers (`importChapters`, `importScene`, `importCodexEntry`).
- Drop the `RevisionSummarizer` constructor dependency.
- Remove the imports that go unused with it: `FieldKind`, `AutosavableFields`, `RevisionSummary`,
  `RevisionOrigin`, `RevisionSummarizer`.
- Update the class docblock's revision-history bullet and the two method docblocks that mention
  sidecars.

**Not** in scope: the manifest version and the path-rejection rule (task 03), the enum and purge
category (task 04).

## Depends on

Nothing (independent of 01), but land it after 01 so the suite never has an exporter writing
sidecars that the importer refuses to read.

## Key decisions

- `ContentSanitizer` **stays** — it still gates `contents.md`, `description.html` and every other
  field file. Only the revision-value `assertHtmlAllowed`/`assertMarkdownAllowed` calls go.
- `ProjectImporter::run()`'s post-phase `SceneReferenceMatcher::syncProject()` is unrelated. Leave it.
- An imported project's history starts the same way a fresh project's does:
  `RevisionRecorder::ensureBaseline()` writes a `baseline` row the first time a writer touches a
  field. Nothing seeds history at import time.

## Tests

- `tests/Unit/Import/ProjectGraphImporterTest.php` — delete the *Revision history import* section
  (~364–520). **New**: a fixture with `includes_revisions: true` **and** a
  `revisions/contents.json` written into the extraction dir imports successfully with
  `Revision::query()->count() === 0`. This is the test that catches a half-removed replay.
- `tests/Unit/Import/ProjectImporterTest.php` — drop the `RevisionOrigin::Import` assertions.
- `tests/Feature/ImportRoundTripTest.php` — **new** assertion: after the existing export → import,
  the new project has zero `Revision` rows.

## Consult

`expanded/architecture.md` → *2. Import* · `expanded/testing.md`.
