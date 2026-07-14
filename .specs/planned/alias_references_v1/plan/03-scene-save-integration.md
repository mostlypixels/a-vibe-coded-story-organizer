# 03 — Wire matcher into scene save

## Scope

Trigger `SceneReferenceMatcher::syncScene()` from the two scene write paths.

**Builds:**
- `SceneController::store` (`app/Http/Controllers/SceneController.php`): after
  `$scene = $chapter->scenes()->create(...)`, call `$matcher->syncScene($scene)` — right next to
  the existing `$scene->mentionedEvents()->sync(...)` call.
- `SceneController::update`: same, after `$scene->update(...)`.
- Inject `SceneReferenceMatcher` into both actions via method injection (matches the existing
  `CodexAsOfResolver $codexAsOf` parameter style on `edit()`).

**Does NOT build:** the matcher itself (task 02, already done), the codex-entry side (task 04),
any UI (05/06).

## Depends on

- **01** (pivot/relations) in `plan/implemented/`.
- **02** (`SceneReferenceMatcher`) in `plan/implemented/`.

## Key decisions already made

- Sync happens **after** the scene row is saved (needs the scene's final `contents` and its
  `id`), same ordering as the existing `mentionedEvents()->sync()` call.
- No conditional skip here (unlike the codex-entry side) — a scene save always resyncs itself;
  there's no "did contents change" optimization in v1 (contents changing is the whole point of
  the feature, and comparing old/new contents to skip a single-scene sync isn't worth the
  complexity `00-overview.md` accepts for the project-wide case).

## Consult

`../expanded/architecture.md` → *Where each trigger calls it*, `00-overview.md`.

## Tests (additions to `tests/Feature/SceneTest.php`)

- Creating a scene whose `contents` mentions a seeded codex entry's alias creates the
  `scene_codex_entry` row (`assertDatabaseHas`).
- Updating a scene to add mentioning text creates the row; removing it and saving again removes
  the row (`assertDatabaseMissing`).
- Saving a scene with no matches leaves no rows (and removes any stale ones from a prior save).
- Non-owner scene update still 403s, unchanged behavior (regression guard — no new gap from the
  added dependency injection).
