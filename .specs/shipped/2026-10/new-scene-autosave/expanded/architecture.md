# Architecture and UI

No new route, model, migration, policy or JavaScript.

## Changes

| File | Change |
|---|---|
| `resources/views/scenes/create.blade.php` | Remove the Description and Contents `x-wysiwyg` fields. |
| `app/Http/Controllers/SceneController.php` → `store()` | Redirect to `scenes.write` for the created scene, not `books.scenes.index`. `SceneSaver::create()` already returns it. |
| `app/Http/Requests/StoreSceneRequest.php` | No change. `rulesFor()` is shared with `UpdateSceneRequest`; `description` and `contents` stay optional there. |
| `documentation/features/` scene guide | One line: a new scene opens in the Write page. Find the page that describes scene creation. |

## Pitfalls

- `StoryController` (`newSceneUrl`), `scenes/index.blade.php` and `editor/empty.blade.php` link to
  `books.scenes.create`. They need no change; check they still read right.
- `HtmlSanitizationTest` posts `contents` to `books.scenes.store`. The request still accepts it,
  so the test stays valid. Do not drop the rule to "clean up".
- Write page focus: `editor/show.blade.php` passes `:autofocus="true"`, but a saved cursor
  position wins (`wysiwyg.js`). A new scene has no saved position, so focus goes to the body.
