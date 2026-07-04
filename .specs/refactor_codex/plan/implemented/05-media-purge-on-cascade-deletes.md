# 05 — Media purge on cascade deletes (finding 2)

## Scope

Stop leaking codex media files when a project or a user account is deleted (the DB cascade
drops `codex_entries` rows without firing `CodexEntry`'s `deleting` hook).

- `app/Services/CodexMediaService.php`: add `purgeProject(Project $project): void` — one
  query plucking `codex_media.path` via
  `whereIn('codex_entry_id', $project->codexEntries()->select('id'))`, bulk
  `Storage::disk(self::DISK)->delete($paths)`. Rows are left to the cascade (mirrors
  `purge()`, same comment style).
- `app/Models/Project.php::booted()`: add a `deleting` hook calling
  `app(CodexMediaService::class)->purgeProject($project)` — placed and commented like
  `CodexEntry`'s hook (lifecycle invariant, the sanctioned `booted()` use).
- `app/Models/User.php`: add a `deleting` hook that deletes projects **through Eloquent**
  — `$user->projects->each->delete()` — so the `Project` hook is the single purge trigger
  (binding decision Q6; `purgeProject` has exactly one caller). Verify the `projects()`
  relation exists on `User` first; add it if missing.

Does **not** touch `applyMediaChanges`/transaction boundaries (task 06) and does not
change any FK cascade — the DB cascade remains the row-cleanup mechanism; hooks rescue
only the files.

## Depends on

Nothing structurally (04 precedes it only by numbering; can run any time after 01).

## Key decisions already made

- Q6: User hook Eloquent-deletes projects rather than calling `purgeProject` directly —
  one mechanism, accepted extra queries at this scale.
- Seeding invariant: `MelusineSeeder` runs `WithoutModelEvents` and seeds no `codex_media`;
  the new hooks must not become load-bearing for seeding.

## Consult

`.specs/refactor_codex/media-lifecycle.md` (§ Finding 2), `open-questions.md` Q6.

## Tests

Template: `CodexMediaTest::test_destroying_an_entry_removes_all_its_media_files`
(`Storage::fake('public')`).

- `test_destroying_a_project_removes_codex_media_files` (in `CodexMediaTest` or
  `ProjectTest`): project with ≥2 entries, each with cover + reference file →
  `delete(route('projects.destroy', $project))` → `assertMissing` every path; rows gone.
  Fails before the fix.
- `test_deleting_the_account_removes_codex_media_files`: same setup, Breeze
  `delete(route('profile.destroy'), ['password' => 'password'])` → files missing. Fails
  before the fix.
- Existing entry-level purge test stays green (no regression from the service addition).
- Non-owner project delete 403 is already covered in `ProjectTest` — no new negative case.
