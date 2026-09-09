# 01 — Story-ordered chapters, shared

## Scope

- `Book::chaptersInStoryOrder()`: the query now private in `SceneController::chaptersFor()`,
  moved onto the model. `chaptersFor()` delegates to it and keeps its `with('act')`.
- `StoryNumbering::fromChapters()`: chapter numbers from an already-ordered chapter
  collection. No act tree, no scene rows.
- Not in scope: any search code.

## Depends on

Nothing.

## Key decisions

- The extraction is earned — the range picker is the second caller. Without it the four
  `orderBy` clauses live in two files and drift the first time act ordering changes.
- `fromChapters()` exists so the picker does not call `forBook()`, which loads every scene
  id and position in the book to number scenes nobody asked for.
- `fromChapters()` must reject nothing and re-sort nothing: it trusts the caller's order,
  the way `fromActs()` trusts its tree. Say so in the docblock — a filtered list would
  compact the numbering.

## Consult

`app/Support/StoryNumbering.php` for `fromActs()`, whose contract this mirrors.

## Tests

`tests/Unit/StoryNumberingTest.php` and the scene tests that already cover `chaptersFor()`.

- `fromChapters()` numbers across an act boundary continuously: last chapter of act 1 is 4,
  first of act 2 is 5.
- `fromChapters()` agrees with `forBook()` on the same book.
- `chaptersInStoryOrder()` returns story order when `position` values are gappy and the
  names sort differently.
- The scene create/edit chapter list is unchanged.
