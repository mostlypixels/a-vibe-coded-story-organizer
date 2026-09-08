# 01 — `App\Support\ListJump`

## Scope

**In:** one new file, `app/Support/ListJump.php`, and its tests.

**Out:** every caller. Nothing wires it up — task 02 (the concern) and tasks 03/04 (the
controllers) do that. This task adds a class nothing yet imports.

## Depends on

Nothing.

## Contract

```php
public static function page(
    Builder $rows,       // the book's unfiltered row query
    string $column,      // 'scenes.chapter_id' | 'chapters.act_id'
    array $orderedIds,   // list<int>, group ids in story order
    int $targetId,
    int $perPage,
): int
```

- Splits `$orderedIds` at `$targetId`, counts `$rows` in the preceding ids, returns
  `intdiv($before, $perPage) + 1`.
- **One count query.** No hydration, no `exists()`, no null return — the caller has already
  checked the target is one of the book's groups.
- A target that is first in `$orderedIds` gives page 1 with no query needed; let the count
  over an empty `whereIn` handle it rather than special-casing.

## Key decisions

- `app/Support`, beside `PageSize` — one static, no state, no dependencies. Not a service:
  this is arithmetic over a query, not a workflow.
- **Direction is not a parameter.** Go to always forces story order ascending, so there is
  no descending case to mirror. Do not add one "for symmetry".
- **No row-value comparison** on the story-order tuple. It would hard-code
  `(acts.position, acts.id, chapters.position, chapters.id)` into this class for no gain —
  the ordered id list is already in memory for the dropdown.

## Tests

Unit-style feature test (`RefreshDatabase`, factories). Fixture: 3 acts × 4 chapters ×
5 scenes = 60 scenes.

- first group → page 1; a middle group → the arithmetically correct page; last group → the
  last page, not past the end.
- exact boundaries: a group whose first row is the last row of a page, and one whose first
  row is the first row of a page. This is where an off-by-one shows.
- `$perPage` of 10 and 25 give different pages for the same target.
- a `$targetId` absent from `$orderedIds` — document and test whatever it does; the concern
  guarantees it never happens, so a defensive throw is acceptable and preferable to a
  silently wrong page.

**The ordering-drift guard belongs here.** Give two sibling chapters the same `position`,
then ask for the page of the second: it must be the page holding its own first scene, not
the first chapter's. See `../expanded/testing.md` → *The ordering-drift guard*. This fails
the moment either side of the ordering contract drops an `id` tie-break.

## Consult

`../expanded/architecture.md` → *`App\Support\ListJump`*.
