# Architecture

No new route, policy or table. `scenes.write` already authorizes `update` on the project.

## Outline data

- New `App\Support\EditorOutline` (read-only lookup, like `SceneNeighbours`). One static method, for example `forBook(Book $book): self`.
- It loads acts → chapters → scenes in three eager-load queries, each level ordered by `(position, id)`. Scene columns: `id`, `chapter_id`, `name`, `position`, `word_count`.
- It holds the loaded acts and a `StoryNumbering::fromActs($acts)`.
- `EditorController::show` uses its numbering for `$context`, in place of `StoryNumbering::forBook()`. `forBook()` loads the same tree, so the page does not load it twice.
- The New scene dialog's `$chapters` come from the outline (acts flattened, in order), in place of `chaptersInStoryOrder()`. One less query. The dialog reads `$chapter->act->name`. Set the relation when you flatten, so it fires no extra query.

> [!WARNING]
> `fromActs()` needs every act, chapter and scene of the book. Do not filter the scene query, for example by status, or the numbers shift.

## Layout preference

- Add `recent` to `EditorLayout::SECTIONS` and to `config('editor.layout.sections')` (value: collapsed or not, see `open-questions.md`).
- Old stored layouts have no `recent` key. `resolve()` fills the default already.
- `rules()` makes `sections.recent` required. `layout.js` sends the full resolved shape, so the client needs no change.
- The `<details>` uses the existing `editor-section-toggle` event and `setSection()`.

## Files to change

| File | Change |
| --- | --- |
| `app/Support/EditorOutline.php` | New |
| `app/Http/Controllers/EditorController.php` | Pass `outline`; numbering and chapters from it |
| `app/Support/EditorLayout.php`, `config/editor.php` | `recent` section |
| `resources/views/editor/partials/recent-scenes.blade.php` | Recent list into a folding section; include the outline |
| `resources/views/editor/partials/outline.blade.php` | New |
| `resources/js/editor/search.js` | `showingRecent()` now also shows the outline; maybe rename to `showingBrowse()` |
| `resources/js/editor/` | Scroll the current row into view (small Alpine `x-init`) |
| `documentation/features/editor.md` | Left panel, `sections.recent`, outline query cost |
