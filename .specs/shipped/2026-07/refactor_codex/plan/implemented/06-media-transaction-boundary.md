# 06 — Media disk I/O out of the transaction (finding 3)

## Scope

Reshape the entry save flow so no disk operation happens inside `DB::transaction`:
decide inside, act after commit; deletes before writes.

- `app/Http/Controllers/CodexEntryController.php` `store()` (`:85`) / `update()` (`:130`):
  the transaction performs only DB mutations and **returns the paths to delete**; after
  `DB::transaction()` returns, delete those files, then store uploads. Shape per the
  snippet in `media-lifecycle.md` (§ Finding 3): `queueRemovals` (row deletes + path
  collection, in-transaction) → `deleteFiles($paths)` → `storeUploads($entry, $request)`
  (cover + reference images/files), both post-commit.
- `app/Services/CodexMediaService.php`:
  - Split `remove()` into row deletion (in-transaction) and file deletion (post-commit);
    keep/adjust the combined path only if something still needs it (`purge`/`purgeProject`
    cover the destroy flows).
  - `storeCover()` becomes two-phase: old cover's **row** deleted in-transaction, its path
    joins the delete list; new cover row+file written post-commit. The single-cover
    invariant is row-level and settles in-transaction.
  - Private `store()` (`:98-108`): wrap the row create in try/catch; on failure unlink the
    just-written file and rethrow (no orphan).
- Update the now-wrong ordering comments at `CodexEntryController:96-97` and `:139`;
  document the accepted post-commit trade-off (entry saved, a late upload failure yields
  fewer media + a visible error — guidelines: explain introduced debt).

Does **not** change validation, the Blade media cards (task 08 touches error rendering
only), or the purge hooks (task 05).

## Depends on

05 (both reshape `CodexMediaService`; serialize to avoid churn).

## Key decisions already made

- Run disk ops after `DB::transaction()` returns — not `DB::afterCommit` (simpler, testable;
  the controller is the only transaction owner).
- Order post-commit: deletes before writes (replaced cover frees its slot).
- Uploads move post-commit too (UploadedFile temp files live for the request), each paired
  with catch-and-unlink.
- `CodexMedia::creating()` position hook must keep firing — rows still created one at a time.

## Consult

`.specs/refactor_codex/expanded/media-lifecycle.md` (§ Finding 3).

## Tests

In `tests/Feature/CodexMediaTest.php` (see `testing.md` § Finding 3 for the two options):

- **Rollback consistency (the review's failure scenario — try this first):** register a
  throwing listener/observer on `CodexMedia` creation within the test, post an update that
  removes image A and uploads image B → assert image A's **row still exists and its file
  still exists on disk** (pre-fix, the file is already deleted when the rollback restores
  the row). This is the fails-before test.
- Happy-path regression: update with removals + uploads → old files missing, new files
  present, rows consistent.
- Orphan cleanup: unit-level test of the private store path — force the row create to
  throw (e.g. the same observer) and assert the just-written file was unlinked.
- If the throwing-observer approach proves too contrived after a genuine attempt, document
  the gap in the test file and keep the happy-path + orphan tests.
