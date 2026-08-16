# No revisions import/export — plan overview

Revision history leaves the archive contract. Pure deletion: no new class, no new route, no new
column — one migration and one new reject rule.

## Execution order

| # | Task | Purpose |
|---|---|---|
| 01 | `export-drops-revisions` | `StaticSiteExporter` stops writing `revisions/` and `includes_revisions`; the toggle leaves the form, the request and the controller. |
| 02 | `import-drops-replay` | `ProjectGraphImporter` stops replaying sidecars — no `Revision` row is ever created by an import. |
| 03 | `archive-contract-v3` | `DATA_VERSION = 3`, `SUPPORTED_MANIFEST_VERSIONS = [3]`, `ImportRules` rejects any `.../revisions/` path. |
| 04 | `retire-import-origin` | `RevisionOrigin::Import` and `RevisionPurger::CATEGORY_IMPORTED` deleted, with the migration that clears existing rows. |
| 05 | `docs-and-changelog` | `export-format.md`, `revisions.md`, `architecture.md`, `CHANGELOG.md`. |

01 and 02 are independent of each other; 03 depends on both (bumping the version while either side
still writes/reads the key leaves the suite in a contradictory state). 04 depends on 02 — the
importer is the last producer of `origin: import`.

## Binding decisions

Settled in the grill. No task re-litigates them.

- **v1 and v2 archives are rejected.** `SUPPORTED_MANIFEST_VERSIONS = [3]`, one entry.
  `ImportValidationException::unsupportedManifestVersion()`'s message is unchanged.
- **`.../revisions/` paths are rejected at the gate**, not allowed-but-ignored.
- **Existing `origin = 'import'` rows are deleted**, never relabelled. `down()` is a no-op.
- **`RevisionOrigin::Import` is deleted outright** — no tombstone case.
- **A stray `include_revisions` POST field is silently ignored** — no `prohibited` rule.
- **The export form is not restyled** now that one checkbox remains.
- `$includeMedia` and the **Include images & files** toggle are untouched throughout.

## Invariants every task must preserve

- **Prune safety.** `Revision::prunable()` matches `origin: automatic` and keeps the newest row per
  `(entity, field)`. Removing a different enum case must not perturb it — if
  `RevisionRetentionAndPurgeTest::test_the_prune_keeps_the_newest_revision_even_when_it_was_inserted_first`
  needs editing, something wider moved than this feature.
- **No history query selects `revisions.value`.** Unchanged here, but any query touched must keep it.
- **Save points stay whole.** `SavePoint::ORIGIN_PRECEDENCE` shortens by one; mixed
  `manual`/`automatic` groups must still resolve to `Manual`. Imported rows minted their own ULIDs,
  so the migration can never leave a half-emptied save point.
- **The security gate keeps its posture.** `ArchiveValidator` reads entries into memory and never
  extracts; `ProjectImporter::extract()` is the only thing that writes to disk, and only after the
  gate passes.
- **`ContentSanitizer` still gates every field file** (`contents.md`, `description.html`, …). Only
  the revision-value calls disappear.
- Authorization is unchanged everywhere: export/import keep the any-authenticated-user posture
  documented in `architecture.md` → *Static site import*.

## Reference docs

`expanded/overview.md` (why, acceptance criteria) · `expanded/architecture.md` (the four seams,
file by file) · `expanded/testing.md` (what to add, what to delete) · `expanded/open-questions.md`
(the resolved decisions and their reasoning).
