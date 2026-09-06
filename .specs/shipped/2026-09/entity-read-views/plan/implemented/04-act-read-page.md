# 04 — Act read page

## Scope

- `show` on the `books.acts` resource (`routes/web.php:168`) → `acts.show`.
- `ActController::show()`: authorize `view` on `$act->book->project`, load
  `chapters.scenes`, `withCount('scenes')`, and `StoryNumbering::forBook($act->book)`.
- `resources/views/acts/show.blade.php`: act number and name, book name, description,
  scene and word counts, then its chapters with their scene titles nested.
- `resources/views/acts/index.blade.php`: name link (line 29) → `acts.show`; add
  `x-icon-view-link` (line 44).

## Depends on

Nothing.

## Key decisions

- **Chapters with scene titles nested**, capped at 20 rows total, with the `showAll`
  disclosure toggle `codex/show.blade.php` uses.
- Story numbers from `StoryNumbering::forBook()` — the whole book, not this act.
- Header actions: edit, history (slug `act`), and the existing delete-with-move dialog.
  The move-to-book control is an editing action and stays on the edit form.
- Chapter and scene rows link to `chapters.edit` / `scenes.edit` for now — those `show`
  routes do not exist until tasks 05 and 06. Task 07 repoints them.

## Consult

`expanded/ui.md` → Page shape; `expanded/architecture.md` → Controllers.

## Tests

In `tests/Feature/ActTest.php`:

- Renders name, description, and the act's story number from the whole book.
- Lists chapters in position order with their scenes nested in order.
- Caps at 20 rows and offers the show-all control past that.
- Omits the chapters card for an empty act.
- No form, input, or autosave field. Non-owner gets 403.
- Index links the name to `show`.
- Assert no N+1 across the chapter and scene lists.
