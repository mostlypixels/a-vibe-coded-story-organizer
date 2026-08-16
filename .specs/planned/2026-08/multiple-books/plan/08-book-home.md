# 08 — `books.show`, the book home

The landing the picker and the book crumb point at.

**Depends on:** 07.

## Scope

- `BookController@show` and `resources/views/books/show.blade.php`.
- Contents: the book's word count, its recent scenes, and links to Overview / Acts / Chapters /
  Scenes.
- `RecentlyEdited::acts/chapters/scenes` accept `Project|Book` and switch the scope query.
  `plotlines` / `events` / `codexEntries` stay project-only.

**Not in scope:** goals or a progress chart — those stay on `projects.show`. The picker and the
crumb that link here are task 09.

## Key decisions

- Deliberately thin, and deliberately **not** a second dashboard. `projects.show` keeps the
  progress chart, the goals, the streak, the recent codex and the project-wide recent scenes.
- Word count is a `SUM` over `Book::sceneQuery()`. No stored count on `books`.

## Consult

`expanded/ui.md` → *Book home (`books.show`)*.

## Tests

- Owner sees their book's word count and only that book's recent scenes; a non-owner gets 403.
- A book with no scenes renders "0 words", never blank.
- `RecentlyEditedTest`: the book-scoped variants return only that book's rows.
