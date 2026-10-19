# 01 — Create form without prose

## Scope

- Remove the Description and Contents fields from `resources/views/scenes/create.blade.php`.
- `SceneController::store()` redirects to `scenes.write` for the created scene.
- One line in the scene feature guide under `documentation/features/`: a new scene opens in the Write page.
- Changelog entry for the change set.
- Deferred to 02: the Write page dialog.

## Depends on

None.

## Key decisions already made

- `StoreSceneRequest` keeps its `description` and `contents` rules: `rulesFor()` is shared with
  `UpdateSceneRequest`, and `HtmlSanitizationTest` posts `contents` to this route.
- Links to `books.scenes.create` (`StoryController`, `scenes/index`, `editor/empty`) stay as they are.

## Docs

- `expanded/overview.md`, `expanded/architecture.md`, `expanded/testing.md`.

## Tests

In `tests/Feature/SceneTest.php`:

- Change the create test: redirect goes to `scenes.write` for the new scene.
- The create page has no `name="contents"` and no `name="description"` field.
