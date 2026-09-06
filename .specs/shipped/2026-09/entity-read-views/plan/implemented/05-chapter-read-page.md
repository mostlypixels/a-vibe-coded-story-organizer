# 05 — Chapter read page

## Scope

- `show` on the `books.chapters` resource (`routes/web.php:175`) → `chapters.show`.
- `ChapterController::show()`: authorize `view` on `$chapter->act->book->project`, load
  `scenes`, `act`, `withSum('scenes as word_count', 'word_count')`, and
  `StoryNumbering::forBook()`.
- `resources/views/chapters/show.blade.php`: chapter number and name, cover image,
  its act, description, word count, scene count, then its scenes in order.
- `resources/views/chapters/index.blade.php`: name link (line 38) → `chapters.show`; add
  `x-icon-view-link` (line 54).

## Depends on

04 (the act page links here).

## Key decisions

- The word-count sum is one aggregate on the query, never a per-row `sum()` in Blade —
  the same pitfall `ChapterController::index()` documents.
- Header actions: edit, history (slug `chapter`), delete-with-move dialog.
- Scene rows link to `scenes.edit` for now; `scenes.show` does not exist until task 06.
  Task 07 repoints them.

## Consult

`expanded/architecture.md` → Controllers; `expanded/ui.md` → Page shape.

## Tests

In `tests/Feature/ChapterTest.php`:

- Renders name, chapter number, act name, description, word count.
- Lists scenes in position order.
- Omits the scenes card for an empty chapter.
- No form, input, or autosave field. Non-owner gets 403.
- Index links the name to `show`.
