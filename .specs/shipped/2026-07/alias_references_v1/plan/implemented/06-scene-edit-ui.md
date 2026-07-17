# 06 — Scene edit page UI

## Scope

The scene-side UI addition from the source spec: a "codex relationships" sidebar, no AJAX.

**Builds:**
- `SceneController::edit`: add `referencedEntries` to the view data —
  `$scene->codexReferences()->with('cover')->orderBy('type')->orderBy('name')->get()`.
- `resources/views/scenes/edit.blade.php`: a "Codex references" `<x-card>` inside the existing
  `<x-slot:sidebar>`, placed after "Share this scene" and before the
  `codex.partials.as-of` include. Empty state, or a list of entry links
  (`route('codex.edit', $entry)`) each showing name + type label. Includes the caption "Detected
  from the scene contents on last save." (makes the no-AJAX behavior explicit to the user).

**Does NOT build:** the matching/write logic (tasks 02-04) — this task's tests seed the
`scene_codex_entry` pivot directly via `attach()`, independent of the matcher.

## Depends on

- **01** (`Scene::codexReferences()`) in `plan/implemented/`.

## Key decisions already made

- No AJAX / live update — the sidebar renders whatever was true as of the last Save, and the
  caption text is required (not optional polish) so this isn't mistaken for a bug.
- Flat list ordered by `(type, name)`, not the heavier `CodexAsOfResolver`-style grouped-by-type
  card — see `../expanded/ui.md` for why (different shape of data, not worth the extra
  abstraction for v1).
- **This card is exclusive to `scenes/edit.blade.php`.** `shared/scenes/show.blade.php` (the
  public share page, `SharedSceneController@show`) is a separate template with no sidebar today
  — do not add this card, or any rendering of `$scene->codexReferences`, there. See
  `../expanded/architecture.md` → *Never appears on the public scene share page*.

## Consult

`../expanded/ui.md` (has the exact Blade snippet), `../expanded/architecture.md` → *Read paths*,
`00-overview.md`.

## Tests (additions to `tests/Feature/SceneTest.php`)

- Scene edit page lists referenced entries, each linking to `codex.edit`.
- The "last save" caption text is present.
- A scene with no references shows the empty state, not an error.
- Non-owner still gets 403 on the edit page (regression guard).
- A scene with a live share link and at least one codex reference: the **public**
  `shared.scenes.show` response never contains the referenced entry's name or a "Codex
  references" heading (same assertion style as the existing `notes`-never-leaks test).
