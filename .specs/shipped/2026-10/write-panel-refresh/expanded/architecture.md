# Architecture

## Route

| Route | Name | Returns |
|---|---|---|
| `GET scenes/{scene}/editor-cards/{type}` | `scenes.editor-cards.index` | The cards of one section as an HTML partial |

- `{type}` is `codex` or `note` (`LinkTargetType`), as in `UpdateEditorCardRequest`. Another value is a 404 through a route constraint.
- Authorize `update` on the scene's project, as `EditorController::show()` does.
- Add the controller action to `EditorCardController` (`index`). It resolves, authorizes, calls `EditorCards`, and returns the view.

## Service

Split `EditorCards::forScene()` so that one section can load alone:

```php
public function codex(User $user, Scene $scene, Project $project): Collection; // CodexCard list
public function notes(User $user, Scene $scene): Collection;                   // NoteCard list
```

- `forScene()` keeps its return shape and calls both. The page-load query count must not change.
- Each method runs its own `editor_cards` query, filtered by target column. A notes refresh does not resolve the codex.

## Partial

- Move the `@foreach ($cards as $card)` loop of `editor/partials/card-section.blade.php` into `editor/partials/cards.blade.php`.
- The page load and the endpoint render that partial, so the two outputs cannot drift.
- The codex card needs `$scene->event`, so the endpoint eager-loads it, as `EditorCardContentController::codex()` does.

## Client

All in `resources/js/editor/cards.js`, in the `editorCards` component. One component per section, so the sections stay independent.

- `refresh()`:
  1. Return if the section's `<details>` is closed, or if a refresh is already running (coalesces `focus` and `visibilitychange`).
  2. Wait for the running card-state saves. Track them in a `Set` of promises in `save()`.
  3. Note the change counter (`changeSeq`, bumped by `setState()`, `close()` and `pick()`), then GET.
  4. Drop the response if `changeSeq` moved during the request. The next trigger refreshes.
  5. Parse into a `<template>`. If every child `isEqualNode()` the current card, do nothing.
  6. Otherwise replace the children of `$refs.cards`. Then put focus back on the same `data-card-id` + `data-card-action` if focus was inside the cards.
- Listen on `window` `focus` and `document` `visibilitychange` in `init()`, and remove in `destroy()`, as `resources/js/autosave/field.js` does.
- Also call `refresh()` when the section's `<details>` opens (its `toggle` event).
- Pass the URL from Blade (`refreshUrl`), as `cardUrl` and `saveUrl` already are.

Rejected: comparing HTML strings. The browser re-serializes the live DOM, and `setState()` changes attributes, so strings differ when nothing changed. `isEqualNode()` compares the parsed trees.

## Pitfall

- A `<button>` inside `<summary>` can toggle the `<details>` on click. Put the refresh icon beside the `<summary>`, not inside it (see `ui.md`).

## Documentation

- `documentation/features/editor.md`: add the route row and a "Refresh" bullet list under Cards.
