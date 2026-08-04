# 02 — `#` column sorts by story order

## Scope

`ChapterController::index` and `SceneController::index` only: replace the
`orderBy('act_id')` / `orderBy('chapter_id')` grouping with a join that orders by the number
the `#` column displays.

Not in scope: rendering the number (task 03).

## Depends on

Nothing. This is a standalone bug fix — `act_id` order only coincides with story order until
someone reorders an act.

## Key decisions

- Chapters: join `acts`, order by `acts.position`, `acts.id`, `chapters.position`,
  `chapters.id`. Scenes: join `chapters` and `acts`, same shape one level deeper.
- **`$direction` must survive.** `x-sortable-header` toggles it and renders a ▼, so
  descending is reachable today (and today produces incoherent order — acts ascending,
  chapters descending inside them). Descending reverses *every* key, so the list reads as
  the story backwards.
- **Table-qualify every column** in both methods, not just the new `orderBy`s: the
  `where('name','like', …)` search filter and the `orderBy($sort, …)` for `?sort=name` both
  become ambiguous once `acts` is joined. `Project::chapterQuery()`'s docblock exists for
  this reason.
- `withCount`/`withSum` already insert `chapters.*`, so the chapters query needs no explicit
  select — and must not gain one *after* them. `SceneController::index` has no aggregate, so
  it needs `select('scenes.*')`.
- `ResolvesIndexSorting`'s allow-list is unchanged; `$sort` still never reaches `orderBy()`
  unvalidated.
- See `expanded/architecture.md` → *Index sorting*.

## Tests

`tests/Feature/ChapterTest.php`, `tests/Feature/SceneTest.php`:

- Regression: create act A then act B, move B above A, sort by `#` → B's chapters come
  first. Fails on today's `orderBy('act_id')`.
- Descending `#` reverses the whole list, not just within each act.
- Sorting and searching by `name` still work with the join in place (guards the ambiguity).
- Word counts and scene counts still render on the chapters list (guards the select order).
