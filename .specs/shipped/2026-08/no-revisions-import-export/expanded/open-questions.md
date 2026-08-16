# Open questions — resolved

All seven were settled in the `mp-plan-tasks` grill. Kept as the record of what was decided and
why; nothing here is still open.

1. **Bump `DATA_VERSION`?** — **Bump to 3, and drop v1/v2 support entirely**
   (`SUPPORTED_MANIFEST_VERSIONS = [3]`). Older archives are rejected at check 4. Pre-V1, nobody
   holds an archive they cannot re-export, and a supported-versions list of one keeps the importer
   free of shape branching.

2. **Delete `origin = 'import'` rows, or relabel them `manual`?** — **Delete.** They are another
   install's edit trail; a "Saved" badge would claim a save that never happened here. `down()` is
   a no-op.

3. **Keep `RevisionOrigin::Import` as a tombstone?** — **No.** No producer, and the migration
   means no row can hold it. Delete the case and let the compiler find the dead `match` arms.

4. **Reject an archive containing `revisions/`, or ignore it?** — **Reject**, in
   `ImportRules::isAllowedPath()`. With v1/v2 gone the only source is a hand-crafted zip, and
   `ProjectImporter::extract()` would otherwise drop the junk on disk.

5. **Should a stray `include_revisions` POST field 422?** — **No.** No rule, so Laravel drops it;
   the export succeeds without revisions.

6. **Restyle the export form now that one checkbox remains?** — **No.** Out of scope.

7. **Anything else reading `includes_revisions`?** — **No.** `ArchiveValidator::MANIFEST_REQUIRED_KEYS`
   is `['version', 'project_id', 'exported_at', 'includes_media']`; the only readers are the
   exporter that writes the key and `ProjectGraphImporter::includesRevisions()`, both deleted here.
