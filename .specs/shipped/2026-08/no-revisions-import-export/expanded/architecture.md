# Architecture

Deletion only — no new class, no new route, no new column. Four seams.

## 1. Export

| File | Change |
|---|---|
| `app/Services/StaticSiteExporter.php` | Delete `addRevisions()` and its 7 call sites; drop the `$includeRevisions` param from `export()`, `addManifest()`, `addStory()`, `addTimeline()`, `addCodex()` and the per-entity writers that only forwarded it; drop the `includes_revisions` manifest key. `use App\Models\Revision` and `use App\Support\AutosavableFields` become unused — remove both. |
| `app/Http/Requests/ExportRequest.php` | Drop the `include_revisions` rule and its comment. |
| `app/Http/Controllers/ExportController.php` | Drop the third `export()` argument. |
| `resources/views/admin/data/export-project.blade.php` | Delete the `include_revisions` checkbox block (~lines 58–71). The **Include images & files** toggle is untouched. |

`$includeMedia` stays exactly as it is — this feature must not touch the media toggle's plumbing
while threading through the same signatures.

## 2. Import

| File | Change |
|---|---|
| `app/Services/Import/ProjectGraphImporter.php` | Delete `importRevisions()`, `includesRevisions()`, `summarizeImported()`, and every `$includeRevisions` parameter/`use` capture down the four phase methods and their helpers (`importChapters`, `importScene`, `importCodexEntry`). Drop the `RevisionSummarizer` constructor dependency. `FieldKind`, `AutosavableFields`, `RevisionSummary`, `RevisionOrigin`, `RevisionSummarizer` become unused imports. |

> [!IMPORTANT]
> `ContentSanitizer` stays — it still gates the field files (`contents.md`, `description.html`, …).
> Only the revision-value calls disappear.

`ProjectImporter::run()`'s post-phase `SceneReferenceMatcher::syncProject()` is unrelated; leave it.

## 3. The archive contract

- `StaticSiteExporter::DATA_VERSION` → **3**; `ImportRules::SUPPORTED_MANIFEST_VERSIONS` → `[3]`.
  A removed manifest key is a breaking layout change under
  `documentation/export-format.md` → *The version contract*. **v1 and v2 archives are rejected**
  at check 4 — pre-V1, nobody holds an archive they cannot simply re-export.
  `ImportValidationException::unsupportedManifestVersion()`'s message is unchanged.
- **`ImportRules::isAllowedPath()` rejects any `.../revisions/` entry.** No v3 export can produce
  one, so a zip carrying them is malformed by definition. Without this they would sit under the
  `data/acts/`, `data/timeline/`, `data/codex/`, `data/project/` allow-listed prefixes and be
  `extractTo()`'d onto disk by `ProjectImporter::extract()` — the validator reads into memory,
  but the import phase extracts the whole zip.
- `ArchiveValidator::validateDescriptors()` only decodes **known** descriptor basenames
  (`act.json`, `scene.json`, …) and the two flat lists, so a sidecar was never JSON-checked
  anyway. The rejection above is the only thing standing between a hand-crafted zip and inert
  junk on disk.

## 4. The `import` origin — "the imported flag"

With no producer, `RevisionOrigin::Import` is unreachable state.

| File | Change |
|---|---|
| `app/Enums/RevisionOrigin.php` | Remove `case Import`, its `label()` arm, and the docblock mention. |
| `app/Services/RevisionPurger.php` | Remove `CATEGORY_IMPORTED`, its `CATEGORIES` entry and its `match` arm. Reword the class docblock's "imported revisions and a two-year `manual` history" rationale — the release valve now exists for the `manual`/`labeled` ratchet alone. |
| `app/Console/Commands/PurgeRevisions.php` | Drop `imported` from the `--category` option description. The unknown-category error already reads `RevisionPurger::CATEGORIES`, so it needs no edit. |
| `resources/views/admin/revisions/edit.blade.php` | Drop the `CATEGORY_IMPORTED => __('Imported')` label and "and imported" from the panel's help text. |
| `app/Support/SavePoint.php` | Drop `RevisionOrigin::Import` from `ORIGIN_PRECEDENCE`. |
| `resources/views/components/revision-origin-badge.blade.php` | Drop the `Import => 'accent'` arm. |

> [!WARNING]
> `RevisionSettingController` loops `RevisionPurger::CATEGORIES` to build the storage panel's rows
> and the purge form's options. It needs no code change — but the panel now shows three rows, and
> its test asserts on them.

### Data model — the migration

The `revisions.origin` column is a plain string cast to the enum; removing a case does not change
the schema, but a surviving `'import'` row **throws on hydration** — history pages, the browser,
prune and purge all read that column.

```php
// database/migrations/xxxx_xx_xx_drop_imported_revisions.php
DB::table('revisions')->where('origin', 'import')->delete();
```

- **Delete, not remap to `manual`.** Those rows are another install's edit trail, replayed here as
  an artifact of a contract that no longer exists. Keeping them relabeled would put a "Saved" badge
  on a save that never happened on this install. Pre-V1, the only data is the Melusine seed
  (`documentation/revisions.md` → *"Why is my history empty?"* set the precedent).
- The `down()` is a no-op — deleted history is not recoverable, and saying so beats a lying stub.
- Any `Revision` whose `save_id` group was entirely `import` rows disappears whole, so no
  half-emptied save point survives; a save point can never mix origins across installs because
  `importRevisions()` minted its own ULIDs.

## Documentation

| File | Change |
|---|---|
| `documentation/export-format.md` | Add a short note under `data/manifest.json` that revision history is deliberately **not** exported and why (size + unprunable imported rows); bump the documented `version` to 3 with the removal noted in the version-contract callout. The sidecar was never documented here, so there is nothing to delete. |
| `documentation/revisions.md` | Prune vs purge table + the categories paragraph lose `imported`; the origin list loses `import`; the `ProjectGraphImporter` replay note under *Summaries* goes. |
| `documentation/architecture.md` | *Static site import* — one line stating revisions are not part of the archive contract. |
| `CHANGELOG.md` | One dated section per `.claude/rules/changelog.md`. |
