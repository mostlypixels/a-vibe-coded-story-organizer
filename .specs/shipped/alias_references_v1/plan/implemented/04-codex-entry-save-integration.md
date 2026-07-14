# 04 — Wire matcher into codex entry save

## Scope

Trigger `SceneReferenceMatcher::syncProject()` from the codex entry write paths, with the
create-always / update-only-if-changed rule.

**Builds:**
- `CodexEntryController::store` (`app/Http/Controllers/CodexEntryController.php`): after
  `$this->syncAliases($entry, ...)` inside the existing `DB::transaction`, always call
  `$matcher->syncProject($project)` — a new entry's alias/name set is trivially "changed" from
  nothing.
- `CodexEntryController::update`: capture the entry's alias set (lowercased, sorted) and `name`
  **before** `$codexEntry->update(...)`/`syncAliases(...)` run; after both run, compare against
  the post-save state; call `$matcher->syncProject($project)` only when they differ. Still inside
  the same `DB::transaction` (DB-only work, consistent with the existing post-commit-disk-only
  split in this controller).
- Inject `SceneReferenceMatcher` into both actions.

**Does NOT build:** the matcher itself (task 02), the scene side (task 03), any UI (05/06).

## Depends on

- **01** (pivot/relations) in `plan/implemented/`.
- **02** (`SceneReferenceMatcher`) in `plan/implemented/`.

## Key decisions already made

- `store` always rescans; `update` rescans only on an actual alias-set or name change — this is
  binding, not a suggestion (see `00-overview.md`).
- Comparison happens **inside** the transaction so "aliases saved" and "references recomputed"
  stay atomic (no window where the DB has new aliases but stale references).
- Entry deletion needs **no** code here — `cascadeOnDelete` on `scene_codex_entry.codex_entry_id`
  (task 01) already removes the pivot rows when `CodexEntry::delete()` runs.

## Consult

`../expanded/architecture.md` → *Where each trigger calls it*, `00-overview.md`.

## Tests (additions to `tests/Feature/CodexEntryTest.php`)

- Creating an entry whose alias matches text already present in an existing scene's contents
  links that scene immediately (no scene re-save required).
- Editing an entry to add/change an alias that newly matches an existing scene's contents links
  it on save.
- Editing an entry's alias so it no longer matches removes the previously-linked scene's row.
- Editing an entry **without** touching aliases or name (e.g. only replacing the cover image or
  editing description) does **not** trigger a project rescan — assert via a spy/mock on
  `SceneReferenceMatcher` (or an equivalent behavioral assertion) that `syncProject` is not
  called, since this is a performance-relevant guarantee that's easy to silently regress.
- Deleting an entry that has referencing scenes cascades the `scene_codex_entry` rows (extend
  `test_destroy_cascades_aliases_tags_and_attribute_values`).
