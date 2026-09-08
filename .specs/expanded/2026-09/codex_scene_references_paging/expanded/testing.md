# Testing

Extend `tests/Feature/ReferencingScenesTest.php`; add `tests/Feature/ReferenceListTest.php`
for the two routes.

## Ordering

- Two books, each with acts: a scene in book 1 act 2 sorts above one in book 2 act 1.
  **Fails before the fix** — this is the regression test.
- Unassigned scenes still sort last, after every evented one.
- Existing single-book ordering assertions must still pass unchanged.

## Caps

- 6 referenced scenes with `search.cap` at 5: the card renders 5 rows and the footer link;
  the 6th scene's name is absent from the response (not merely hidden — assert
  `assertDontSee`, which the Alpine version would have failed).
- Exactly `cap` rows: no footer link.
- All four cards, since each is a separate Blade.

## Full pages

- Page 1 and page 2 of a 3-page set return disjoint rows, and page 2's `page=2` round-trips.
- Rows-per-page follows the user's `page_size`, not a constant (mirror
  `PageSizePreferenceTest`).
- Empty state renders when the entry has no references.
- Non-owner → 403 on both routes.
- Codex page order matches `ReferencingScenes::forEntry()` across a page boundary — the
  point of paging the sorted collection rather than the query.

## Book naming

- Single-book project: no book name in any row.
- Two books, one unnamed: the unnamed one prints the project name, and `#` appears nowhere.
