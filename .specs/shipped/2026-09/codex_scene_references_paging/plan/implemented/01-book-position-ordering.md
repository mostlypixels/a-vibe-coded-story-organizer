# 01 — Add `book.position` to the codex→scenes sort key

## Scope

- `App\Services\ReferencingScenes::forEntry()`: put `book.position` in front of
  `act.position`. Six sort parts become seven.
- Eager load `chapter.act.book` in place of `chapter.act`.
- Nothing else. No view, route, or controller changes.

## Depends on

Nothing. First task.

## Key decisions

- This is a bug fix on its own, not folded into task 02's extraction. Acts hang off a
  book, so without the book term a scene in book 2 act 1 sorts above one in book 1 act 2.
- The book term joins the manuscript tiebreak, after the event terms. Evented scenes still
  sort by `(event_datetime, event id)` first, and unassigned scenes still sort last.

## Consult

`expanded/architecture.md` → *`App\Services\ReferencingScenes`*.

## Tests

Extend `tests/Feature/ReferencingScenesTest.php`.

- Two books, each with acts: a scene in book 1 act 2 sorts above one in book 2 act 1.
  **Must fail before the fix.**
- Unassigned scenes still sort last, after every evented one.
- Existing single-book ordering assertions pass unchanged.
