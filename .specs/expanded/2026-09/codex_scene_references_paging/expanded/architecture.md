# Architecture

## `App\Services\ReferencingScenes`

- Sort key gains `book.position` in front of `act.position`. Six parts become seven.
- Eager load becomes `chapter.act.book`, plus `event`.
- Add `forScene(Scene $scene): Collection` so both directions live in one service.
  It is `codexReferences()->with('cover')->orderBy('type')->orderBy('name')` — SQL order,
  moved out of `SceneController::edit()` and `::show()`, which duplicate it today.

The codex direction sorts in PHP, so a SQL `LIMIT/OFFSET` would page the wrong set.
Same constraint as `SearchController::domain()`; take the same answer.

## Routes

Shallow, beside the existing shallow codex/scene routes in `routes/web.php`.

| Verb | URI | Name | Action |
|---|---|---|---|
| GET | `/codex/{codexEntry}/scenes` | `codex.scenes.index` | `CodexEntryController::scenes()` |
| GET | `/scenes/{scene}/codex-references` | `scenes.codex-references.index` | `SceneController::codexReferences()` |

Two actions, not one parameterized one — they bind different models and render
different rows.

## Controller actions

Both: `authorize('view', $model->project)`, then hand the full collection to a paginator.

- Codex direction: hand-built `LengthAwarePaginator` over `forPage()`, copying
  `SearchController::domain()` exactly, including `Paginator::resolveCurrentPath()`.
- Scene direction: the order is SQL, so use a real `->paginate(PageSize::resolve(...))`
  on the `codexReferences()` relation. Do not hand-build one where the query can do it.

Note the asymmetry in a comment on each action, or the next reader will "fix" one to
match the other.

## Config

`config/search.php` `cap` is the shared inline cap. Reuse it rather than adding
`config/references.php` — one number, one meaning, and a second file invites drift.
Rename nothing; add a line to that file's comment saying the reference cards read it too.

## Controllers that change

- `CodexEntryController::show()` / `::edit()` — pass the full collection still; the view
  caps. (Matches how `search.index` works.)
- `SceneController::edit()` / `::show()` — replace the inline query with
  `ReferencingScenes::forScene()`.
