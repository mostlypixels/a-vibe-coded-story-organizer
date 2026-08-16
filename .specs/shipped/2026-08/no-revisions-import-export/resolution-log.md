# No revisions import/export — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

> [!IMPORTANT]
> An **exception log, not a work journal**. A task that went to plan gets no entry — the
> diff and the task file already record what was built. Bullets under the headings below,
> root cause first, no per-task sections.

## Feedback & decisions

- **v1/v2 archives are rejected, not migrated.** `SUPPORTED_MANIFEST_VERSIONS = [3]`. The expanded
  spec originally proposed `[1, 2, 3]` with old sidecars ignored; the grill chose the harder break —
  pre-V1, nobody holds an archive they cannot re-export, and a one-entry list keeps the importer
  free of shape branching.
- **`.../revisions/` paths are rejected at the gate**, not left allowed-but-inert. Only a
  hand-crafted zip can carry them now, and `ProjectImporter::extract()` would otherwise drop the
  junk on disk.
- **Existing `origin = 'import'` rows are deleted, not relabelled `manual`** — a "Saved" badge would
  claim a save that never happened on this install. `down()` is a no-op.
- **`RevisionOrigin::Import` is deleted outright**, no tombstone case.
- A stray `include_revisions` POST field is silently ignored (no `prohibited` rule); the export form
  is not restyled around its one remaining checkbox.

## Deviations from the spec/plan

- Two claims in the expanded `architecture.md` were wrong and were corrected before planning:
  `ArchiveValidator` does **not** JSON-check `revisions/<field>.json` (`validateDescriptors()` only
  decodes known descriptor basenames), and it never reads `includes_revisions`
  (`MANIFEST_REQUIRED_KEYS` never listed it).
- The retention card's help text on `admin/revisions/edit.blade.php` also claimed "imports are never
  removed by this". Only the storage panel's text was in scope, but leaving the other one would have
  named an origin that no longer exists — both were reworded.

## Issues → resolutions

- `ProjectImporterTest::makeValidUpload()` carried a `revisions/contents.json` sidecar (task 02's
  "never replayed even when present" fixture). Once `ImportRules::isAllowedPath()` rejects any
  `revisions/` segment, that fixture is no longer a *valid* upload — every test built on it failed
  at the gate. Removed the sidecar and `includes_revisions` key from the shared fixture; the
  "import never replays history" assertion in `ProjectGraphImporterTest` still covers the intended
  behavior (it writes files straight to the working directory, bypassing `ArchiveValidator`).
- `RevisionDataModelTest::test_prunable_excludes_non_automatic_origins_regardless_of_age` had to be
  edited even though `prunable()`'s guard is untouched. Root cause: it hard-coded
  `assertCount(4, $prunableIds)` — one automatic sibling per non-automatic origin in its own loop, so
  the number was fixture arity, not a claim about pruning. It now counts the non-automatic rows it
  seeded, so removing or adding an origin can never make it wrong again.
- Three `assertDatabaseCount('revisions', 4)` assertions in `RevisionRetentionAndPurgeTest` broke for
  the same reason — `seedOneRevisionPerCategory()` now seeds three rows.
