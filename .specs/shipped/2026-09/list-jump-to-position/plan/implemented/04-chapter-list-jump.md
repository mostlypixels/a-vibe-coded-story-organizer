# 04 — Chapter list jump

Task 03's pattern, applied to acts. If this needs a new idea, 03 got something wrong.

## Scope

**In:**

- `ChapterController::index` — `$perPage` extracted, `jumpRedirect()` after `authorize()`,
  return type widened. Passes `'chapters.act_id'`, `actsFor($book)`, `$book->chapterQuery()`,
  fragment prefix `act`.
- `resources/views/chapters/index.blade.php` — **Go to** button on the act select,
  highlighted rows, anchor id.
- `tests/Feature/ListJumpTest.php` — the act cases added to the existing file.

**Out:** the page-range line → task 05.

## Depends on

01, 02, 03.

## Key decisions

- Same shape as 03 throughout. `x-table-row` already has `highlighted` from 03 — reuse, do
  not extend.
- Anchor prefix is `act-<id>`.
- No `<optgroup>`s — acts are a flat 3–6 options.
- **The act list itself gets no jump.** 3–6 rows, no page to reach.

## Watch for

`ChapterController::index` clones `$filtered` *before* `withCount`/`withSum` and its comment
explains that a later `select()` would drop those aliases. The jump redirect returns before
any of that runs, so it cannot interact — but place the call early, after `authorize()`, not
somewhere in the middle of the query building.

`actsFor()` orders by `position` only, with no `id` tie-break, while `index()` orders
`acts.position, acts.id`. **That is a real drift** of the kind task 01's guard test exists
to catch. Fix `actsFor()` to match, and say so in `resolution-log.md`.

## Tests

Per `../expanded/testing.md` → *Acts list*: lands on the right page, search cleared,
foreign act id, non-owner 403. Plus the two-acts-sharing-a-position ordering-drift case.

## Consult

`../expanded/architecture.md` → *Controller changes*. `../expanded/ui.md`.
