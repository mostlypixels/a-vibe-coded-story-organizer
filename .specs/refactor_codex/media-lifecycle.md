# Media lifecycle — findings 2, 3

Both findings are about `CodexMediaService` file operations happening at the wrong moment
relative to DB row lifecycle: finding 2 is files *never* deleted (cascade bypasses the model
hook), finding 3 is files deleted/written *too early* (inside an open transaction).

## Current state

- File cleanup lives in `CodexEntry::booted()`'s `deleting` hook
  (`app/Models/CodexEntry.php:36-38`) → `CodexMediaService::purge`
  (`app/Services/CodexMediaService.php:85-92`). Fires only on Eloquent-initiated entry deletes
  (`CodexEntryController@destroy:156`).
- `ProjectController@destroy` (`app/Http/Controllers/ProjectController.php:52`) calls
  `$project->delete()`; `codex_entries.project_id` is `cascadeOnDelete` — rows drop at the DB
  level, **no model events fire**, `purge` never runs.
- `ProfileController@destroy` (`app/Http/Controllers/ProfileController.php:53`) calls
  `$user->delete()`; the users → projects cascade is likewise DB-level, so it bypasses even a
  `Project` `deleting` hook.
- `CodexEntryController::applyMediaChanges` (`:209-231`) runs inside `DB::transaction` in both
  `store()` (`:85`) and `update()` (`:130`): removals first (`CodexMediaService::remove` —
  disk delete *then* row delete), then `storeCover` (which may `remove()` the old cover), then
  `storeMany` uploads (disk write *then* row create, per file).

## Finding 2 — purge media on project *and* user deletion

### Design

Add a bulk purge to the service, and call it from `deleting` hooks on **both** `Project` and
`User` (a `Project` hook alone does not cover account deletion, because the user→project
cascade also skips model events):

```php
// CodexMediaService
/**
 * Delete every codex media file under a project (or a user's projects) before a
 * DB cascade drops the rows. One query over codex_media joined through entries;
 * rows are left to the cascade, mirroring purge().
 */
public function purgeProject(Project $project): void
{
    $paths = CodexMedia::query()
        ->whereIn('codex_entry_id', $project->codexEntries()->select('id'))
        ->pluck('path')
        ->all();

    if ($paths !== []) {
        Storage::disk(self::DISK)->delete($paths);
    }
}
```

- `Project::booted()` (`app/Models/Project.php:55`) gains:

  ```php
  static::deleting(function (Project $project) {
      app(CodexMediaService::class)->purgeProject($project);
  });
  ```

  Same placement and comment style as `CodexEntry`'s hook — this is a *lifecycle invariant*,
  the sanctioned use of `booted()` per `.claude/guidelines.md`.
- `User` model gains the mirror hook iterating its projects
  (`$user->projects->each(...)` → `purgeProject`), since Breeze's profile destroy deletes the
  user directly. `User::projects()` exists (`User` has many `Project`s per CLAUDE.md); verify
  the relation name before wiring.
- Alternatively the `User` hook can simply `$user->projects->each->delete()` (Eloquent-delete
  the projects so the `Project` hook fires) — one mechanism instead of two callers of
  `purgeProject`. Slightly more queries, meaningfully less duplication; either is acceptable,
  prefer this one for DRY unless project counts are expected to be large (they are not).

### Non-goals

- Do not switch the FK cascades to Eloquent-level cascades generally; the DB cascade stays the
  row-cleanup mechanism (fast, correct). Hooks only rescue the *files*.
- `WithoutModelEvents` seeding is unaffected: the seeder never seeds `codex_media` (CLAUDE.md),
  so there is nothing to leak on re-seed.

## Finding 3 — move disk I/O out of the transaction

### Failure modes today

1. **Rollback after a removal**: `remove()` deletes the file first; if a later step in the
   transaction throws, the rollback restores the media *row* but the file is gone → `url()`
   404s with no way to detect or repair.
2. **Rollback after an upload**: files written by `store()` before a later failure survive the
   rollback as unreferenced orphans on disk. (The comment at `CodexEntryController:96-97`
   claims write-last ordering prevents orphans, but only for failures in the *earlier,
   non-media* steps.)

### Design — decide inside, act outside

Split `applyMediaChanges` around the transaction boundary. Inside the transaction: only DB
mutations. Disk deletes: after commit. Disk writes: also after commit (uploads are still
available then — `UploadedFile` temp files live for the request), each write paired with its
row insert so a failed write can unlink before rethrowing.

Concretely, in `store()` / `update()`:

```php
$pathsToDelete = DB::transaction(function () use (...) {
    // ... entry create/update, syncAliases, tags sync, seedAttributeBaselines ...

    // Row-only media pass: delete rows for remove_media[] (and a replaced cover),
    // collect their paths; do NOT touch the disk yet.
    return $media->queueRemovals($entry, $request->validated('remove_media', []));
});

// Disk operations after commit: deletes can no longer be rolled back into
// broken rows, and upload failures no longer poison the entity write.
$media->deleteFiles($pathsToDelete);
$media->storeUploads($entry, $request); // cover + reference images/files
```

Service-side reshaping (`CodexMediaService`):

- `remove(CodexMedia $media)` splits into row deletion (in-transaction) and file deletion
  (post-commit). Keep a combined `remove()` for the single-row path if the entry-destroy flow
  still wants it, but `purge`/`purgeProject` already cover that.
- `storeCover` becomes two-phase too: the old cover's row is deleted in the transaction (its
  path joins `$pathsToDelete`); the new cover row+file are written post-commit.
- `store()` (private, `:98-108`) gains failure cleanup:

  ```php
  $path = $file->store(self::DIRECTORY, self::DISK);

  try {
      return $entry->media()->create([...]);
  } catch (Throwable $e) {
      Storage::disk(self::DISK)->delete($path); // don't orphan the file
      throw $e;
  }
  ```

- `Laravel's DB::afterCommit(...)` is an equivalent mechanism for the deletes; running them
  after the `DB::transaction()` call returns is simpler and testable — prefer that (the
  controller is the only transaction owner here).

### Consequences / trade-offs

- Post-commit upload failure now yields a *saved entry with fewer media than requested* plus a
  visible 500 — strictly better than a rolled-back entry edit **and** disk corruption.
  Document this in the method comment (guidelines: "if technical debt is introduced, explain
  why").
- The `CodexMedia::creating()` position hook is unaffected (rows still created one at a time).
- Order within post-commit stays: deletes before writes, so a replaced cover frees its slot
  (the single-cover invariant is row-level and now settled in-transaction anyway).
