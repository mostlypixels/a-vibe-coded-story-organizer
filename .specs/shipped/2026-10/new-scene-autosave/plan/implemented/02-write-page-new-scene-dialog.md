# 02 — Write page "New scene" dialog

## Scope

- A **New scene** button at the top of the left panel (`resources/views/editor/partials/recent-scenes.blade.php`), above the search.
- A dialog (`x-dialog`, like `scenes/partials/quick-codex-entry.blade.php`) with Chapter and Title.
- A JSON endpoint that creates the scene and returns the new scene's Write URL.
- Alpine component posts in the background, shows a 422 error inside the dialog, and on success opens the URL.
- Changelog entry (same dated section as 01 if one PR).

## Depends on

01.

## Key decisions already made

- New controller `QuickSceneController::store()`, the same shape as `QuickEventController`.
  Route `POST /books/{book}/scenes/quick`, name `books.scenes.quick-store`, registered before
  the scenes resource.
- New `StoreQuickSceneRequest`: `chapter_id` (same rule as `StoreSceneRequest::rulesFor()`) and
  `name` (`StoreSceneRequest::fieldRules()`). Status is set to Draft on the server, not sent.
- Response contract:

  ```json
  201 { "url": "<route('scenes.write', $scene)>" }
  ```

- `EditorController::show()` passes the book's chapters (`$book->chaptersInStoryOrder()`) and
  the current chapter id for the default.
- Before it opens the URL, the component calls `settleAutosaves(Alpine.store('autosave'))`. When
  that returns false, it opens the `unsaved-changes-guard` modal instead of leaving.
- Enter in the Title field submits the dialog. The dialog stays outside any form.

## Docs

- `expanded/overview.md`, `expanded/architecture.md`; `plan/00-overview.md` → Decided.

## Tests

- Feature: owner creates → 201 with the Write URL, scene is Draft at the end of the chapter;
  non-owner → 403; chapter from another book → 422; empty name → 422.
- Feature: the Write page shows the New scene button and the book's chapters in the dialog.
- Vitest next to the new JS: 422 shows the error; success with a failed flush opens the guard
  modal and does not navigate.
- Browser check (`run-imagoldfish`): type in a scene, open the dialog at once, create → the old
  scene's text is saved and the new scene opens in Write.
