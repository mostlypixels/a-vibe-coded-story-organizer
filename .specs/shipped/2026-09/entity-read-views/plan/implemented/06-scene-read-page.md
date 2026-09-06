# 06 — Scene read page

## Scope

- `show` on the `books.scenes` resource (`routes/web.php:181`) → `scenes.show`.
- `SceneController::show()`: authorize `view` on `$scene->chapter->act->book->project`,
  load `chapter.act.book`, `event`, `mentionedEvents`, `codexReferences.cover` ordered by
  `(type, name)` — copy that query from `edit()` — and `StoryNumbering::forBook()`.
- `resources/views/scenes/show.blade.php`: name, status badge, word count, position hint,
  its chapter and act, description, prose via `x-scene-prose`, notes card, the codex
  entries it references, its "happens during" event, and its mentioned events.
- `resources/views/scenes/index.blade.php`: name link (line 40) → `scenes.show`; add
  `x-icon-view-link` (line 65).

## Depends on

01 (`x-scene-prose`), 05.

## Key decisions

- **The prose renders in full**, not behind a toggle. The prose is the scene.
- **Notes get their own card below the prose.** It is the writer's page, behind login.
- No `CodexAsOfResolver`, no `EventWindow`, no `DuplicateName` — all editing concerns.
- Header actions: edit, duplicate dialog (`scenes.duplicate`), history (slug `scene`),
  delete. Sharing stays on the edit form.

## Consult

`expanded/ui.md` → Scene prose; `expanded/architecture.md` → Controllers.

## Tests

In `tests/Feature/SceneTest.php`:

- Renders name, status, word count, chapter and act, description, and the rendered prose.
- Renders the notes card, and omits it when notes are empty.
- Lists referenced codex entries, ordered by type then name.
- A scene with no event still renders; the "happens during" line is omitted.
- No form, input, or autosave field. Non-owner gets 403.
- Index links the name to `show`.
