# Write page (editor)

[Documentation](../README.md) › [Features](README.md) › Write page

A full-height page for the prose of one scene, with a side panel on each side. `App\Http\Controllers\EditorController`, `resources/views/editor/`, `resources/js/editor/`.

## Routes

| Route | Purpose |
| --- | --- |
| `GET projects/{project}/write` (`projects.write`) | Opens the scene with the newest write. Empty state when the project has no scene. |
| `GET scenes/{scene}/write` (`scenes.write`) | The page. |
| `PUT scenes/{scene}/editor-cards` (`scenes.editor-cards.update`) | Saves one card state. |
| `GET codex/{codexEntry}/card`, `GET notes/{note}/card` | Card body HTML. |
| `PATCH preferences/editor-layout` (`preferences.editor-layout`) | Saves panel widths and collapse state. |

- Search uses the existing `projects.link-targets` endpoint (`LinkTargetType::candidates()`).
- Scene routes authorize `update` on the scene's project. Card reads authorize `view` on the target's project.
- A foreign scene, entry or note id is 404. Ids resolve through `LinkTargetType::queryFor($project)`.
- The page edits prose only (`scene.contents`, through `x-autosave-field`). Title, status, chapter and event stay on `scenes.edit`.

## Layout preference

- Stored per user in `users.editor_layout` (JSON), read only through `App\Support\EditorLayout::resolve()`.
- Shape: `left` and `right` (`width`, `collapsed`), and `sections` (`codex`, `notes`; `true` means collapsed).
- `resolve()` fills defaults from `config/editor.php`, drops unknown keys and clamps widths. Never use the raw column.
- The endpoint writes `$request->user()` only, like `PageSizeController`. It has no policy.
- `layout.js` saves 500 ms after the last change. It always sends the full shape.
- Below `lg`, panels start collapsed and an open panel is a drawer over the prose. Stored widths do not apply, and a section toggle there is not saved.

## Cards

- A card shows a codex entry or a note, read-only, with a link to the full page in a new tab.
- State is in `editor_cards`, per user and per scene: `expanded`, `collapsed` or `closed` (`EditorCardState`).
- A row has exactly one of `codex_entry_id` and `note_id`. `EditorCard::saveState()` is the only writer, because the unique index treats a NULL target as distinct.
- Each section keeps its own cards: a codex pick opens in Codex, a note pick in Notes.
- The scene's linked notes (`$scene->notes`) show as collapsed cards with no row. A `closed` row hides one. A new search pick reopens it.
- A codex card shows attributes as of the scene's "happens during" event (`CodexAsOfResolver::resolveEntries()`, one query set for all cards). An entry that does not exist at that moment still shows its card, marked "Not present at this scene's moment", with its description and no values.
- Codex search matches names and aliases. The result label stays the entry name. No attribute search.
- `EditorCards::forScene()` builds the page-load cards. A test pins the query count, so it must not grow with the card count.

## Scene switch is a full page load

- Previous/next links, recent scenes and scene search are plain links. The navigation guard (`resources/js/navigation-guard.js`) waits for every pending autosave, then follows the link. It shows the "Unsaved changes" dialog only when a save fails: a conflict or a network error.
- The load also releases the tab lock. An in-place swap of the editor would need both done by hand.
- Previous/next follow story order inside the scene's book (`SceneNeighbours`). They are hidden at the ends.
- "Today" in the header uses the figure of the dashboard "Today" bar. It updates from the `word-count:reconcile` event that autosave sends.

## Browser storage

| Key | Holds | Why not the account |
| --- | --- | --- |
| `editor-position:{scene id}` (`localStorage`) | Scroll position and cursor of the prose | Screen size and browser differ between computers |

- Every access is in try/catch. Without storage, the page works and opens at the end of the text.
- Cards and layout are on the account, so they follow the user to another computer.

## Pitfalls

> [!WARNING]
> Keep the Tiptap `Editor` out of Alpine reactive data. See [Rich text](rich-text.md#editor).

- The right `<details>` sections sit inside `editorCards`, so they dispatch `editor-section-toggle`. The layout root listens. A direct call would run in the merged Alpine scope and hit the card `save()`.
- `x-editor-card` has `data-card-*` hooks. The JS copies an empty card from a `<template>` for a new pick, so keep the hooks when you change the markup.
- Scroll restore runs after the editor is ready. Before that its box has no height and `scrollTop` clamps to 0.
- Out of scope: quick note creation, auto-opening mentioned entries, editing inside cards, panel keyboard shortcuts.

## Related documentation

- [Rich text](rich-text.md)
- [Revisions](revisions.md)
- [Writing progress](writing-progress.md)
- [Components](../interface/components.md)
