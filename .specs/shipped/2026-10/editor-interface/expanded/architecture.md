# Architecture

## Routes

All in the `auth` group of `routes/web.php`.

| Method | URI | Name | Action |
| --- | --- | --- | --- |
| GET | `/projects/{project}/write` | `projects.write` | `EditorController@home` |
| GET | `/scenes/{scene}/write` | `scenes.write` | `EditorController@show` |
| GET | `/codex/{codexEntry}/card` | `codex.card` | `EditorCardController@codex` |
| GET | `/notes/{note}/card` | `notes.card` | `EditorCardController@note` |
| PATCH | `/preferences/editor-layout` | `preferences.editor-layout` | `EditorLayoutController@update` |

Shallow: the scene, entry and note bind directly. The project comes from `RouteContext`.

## Controllers

- `EditorController@home`: authorize `view` on project. Redirect to `scenes.write` of `$project->sceneQuery()->latest('updated_at')->first()`. No scene: render `editor.empty`.
- `EditorController@show`: authorize `update` on `$scene->book()->project` (same as `SceneController@edit`). Pass scene, project, recent scenes, resolved layout, the scene's event.
- `EditorCardController`: authorize `view` on the owning project. Return a Blade partial (HTML), not JSON. The partial uses `x-rich-text` with the project, so `entity:` links resolve the same as everywhere.
  - `codex` takes optional `?scene=<id>`. Resolve it with `$project->sceneQuery()->findOrFail()`. A scene of another project is 404, not a leak of its event.
- `EditorLayoutController@update`: `UpdateEditorLayoutRequest` (rules from `EditorLayout::rules()`), save to the acting user, return 204. No policy, like `PageSizeController`.

## Reused services

| Need | Reuse | Change |
| --- | --- | --- |
| Recent scenes | `App\Services\RecentlyEdited::scenes()` | Add `string $route = 'scenes.edit'` so the editor gets `scenes.write` URLs. Limit 15 from `config/editor.php`. |
| Scene, codex, note search | `projects.link-targets` (`LinkTargetController`, `LinkTargetType::candidates()`) | None. JS builds the target URL from the id. |
| Codex values as of the scene | `App\Services\CodexAsOfResolver` | Add `resolveEntry(CodexEntry, ?Event)`. Second caller of the same rule, so extract from `entriesForType()`. Eager-load the same relations for one entry. |
| Prose editor | `x-autosave-field entity="scene" field="contents" :quick-codex-entry="true"` | None. Autosave, revisions, tab lock and conflicts come with it. |
| Leave guard | `navigationGuard` | None. A scene pick is a link, so the guard flushes pending saves first. |

## Scene switch: full page load

A pick in the left panel navigates to `scenes.write`. Rejected: swapping only the editor with fetch. It must tear down Tiptap, the autosave store entries and the tab-lock claim without losing a pending save. The page load already does that, and a reload is fast.

Cost: open cards would be lost. Keep the open card list in `sessionStorage`, key `editor-cards:<project id>`, as `[{type, id, collapsed}]`. On load, refetch each card. Per-tab convenience only, per the browser-storage rule. Wrap every access in try/catch.

## Navigation

- `App\Support\ProjectNavigation`: add `writeActive` (route `projects.write` or `scenes.write`). Set `homeActive` false there.
- `components/navigation/project-menu.blade.php` and `responsive-project-menu.blade.php`: `x-nav-link` "Write" after Dashboard, to `projects.write`.
- Breadcrumbs: project › book › "Write" › scene name.

## Frontend modules

| File | Role |
| --- | --- |
| `resources/js/editor/layout.js` | Alpine `editorLayout`: widths, collapsed flags, resize drag and keys, debounced PATCH (500 ms after the last change) |
| `resources/js/editor/cards.js` | Alpine `editorCards`: card list, fetch card HTML, `sessionStorage` sync, no duplicates (picking an open card moves focus to it) |
| `resources/js/editor/search.js` | Thin wrapper on `candidate-search.js` for the three search fields |

Register them in `resources/js/app.js` like the others. Keep the Tiptap editor out of these components (Alpine proxy warning in `rich-text.md`).

## Documentation

- New `documentation/features/editor.md`. Link it from `documentation/features/README.md` and the architecture feature table.
- `CHANGELOG.md` section.
