# 02 — `ReferencingScenes::forScene()` and the dead cover load

## Scope

- Add `forScene(Scene $scene): Collection` to `App\Services\ReferencingScenes`:
  `codexReferences()->orderBy('type')->orderBy('name')`.
- Replace the inline copy of that query in all three current call sites:
  `SceneController::edit()`, `SceneController::show()`, and
  `SceneCodexEntryController::store()`.
- Drop `->with('cover')`. Nothing renders a cover on the scene side, and task 06 keeps it
  that way.
- Not in scope: capping, routes, pages, view changes. Behaviour is identical after this
  task.

## Depends on

01.

## Key decisions

- Worth extracting for three callers, not two as `open-questions.md` assumed — the AJAX
  refresh in `SceneCodexEntryController::store()` is the third, and task 05 makes a fourth.
  The eager-load list is the part that drifts.
- `forScene()` returns a `Collection` here. Task 05 needs a paginated query, so it may add
  a query-returning sibling rather than paginating a loaded collection — that call is
  task 05's.

## Consult

`expanded/architecture.md` → *`App\Services\ReferencingScenes`* and *Controllers that change*.

## Tests

- `ReferencingScenesTest`: `forScene()` returns the entries in `(type, name)` order.
- Existing scene edit/show and quick-codex-entry tests pass unchanged — the point of the
  task.
