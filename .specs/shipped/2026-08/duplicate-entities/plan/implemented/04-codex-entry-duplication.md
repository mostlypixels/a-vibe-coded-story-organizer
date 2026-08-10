# 04 — Codex entry duplication (backend)

**Depends on:** 01, 02 (for `DuplicateEntityRequest`).

## Scope

* `CodexMediaService::copyFile(string $path): string` — copies a stored file to a freshly
  generated path under the service's own directory. The naming/disk knowledge stays in the
  service, like `storeImportedFile()`.
* `App\Services\CodexEntryDuplicator::duplicate(CodexEntry $entry, string $name): CodexEntry`.
* `POST /codex/{codexEntry}/duplicate` → `codex.duplicate`, beside the flat codex edit/update/
  destroy routes.
* `CodexEntryController::duplicate()`, reusing `DuplicateEntityRequest`.

Not in scope: Blade changes (task 05).

## Key decisions

* **Files first, transaction second.** Copy every media file to a fresh path, insert the rows in a
  transaction, and `CodexMediaService::deleteFiles()` the copies in a `catch` before rethrowing.
  This deliberately inverts the project's "disk after commit" convention — the rows must carry the
  new paths. See `expanded/architecture.md` → *order of work*.
* Copied as new rows: aliases, `codex_media` (with new paths), `codex_attribute_values` (same
  `codex_attribute_id` + `start_event_id`; the unique index is per entry, so no collision).
* Tag pivots re-attach the **existing** tag rows — never `firstOrCreate` a tag here.
* A media row with `path === null` (metadata-only import) copies as `path === null`, no file work.
* `SceneReferenceMatcher::syncProject($project)` after commit: the copy adds a new name and a
  copied alias set. The resulting doubled references in scene sidebars are expected and accepted.
* Redirect to `codex.edit` on the copy with `->with('status', 'duplicated')`.

## Consult

`expanded/architecture.md` → *CodexEntryDuplicator*; `expanded/data-model.md` → *What each
duplicate copies*, *Files*.

## Tests

Extend `tests/Feature/CodexEntryTest.php` with the *Both entities* and *Codex entry* sections of
`expanded/testing.md`, plus the *Failure path* case: with `Storage::fake('public')`, force the
transaction to throw after the copies and assert no copied file survives. Cover the
delete-the-original-keeps-the-copy's-file case explicitly — it is the regression this feature is
most likely to introduce.
