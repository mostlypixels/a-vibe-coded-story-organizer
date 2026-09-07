# 04 — Paginate codex entries

## Scope

- `CodexEntryController::index`: `->get()` → `->paginate(...)->withQueryString()`.
- The bar in `resources/views/codex/index.blade.php`.

## Depends on

03.

## Not in scope

- Scenes, chapters, acts, books — task 05.
- The codex home page (`codex/home.blade.php`) is not one of the nine indexes.

## Key decisions

- `$names` stays a single project-wide, type-scoped `pluck()`. It is the list
  `DuplicateName::suggest()` compares **against**, not a list of rows — never
  paginate it. See `expanded/architecture.md` → Must stay unpaginated.
- `$duplicateNames` keeps mapping over the result set. Because that set is now
  one page, the per-row cost the spec calls out fixes itself: 300 calls become
  100. Do not restructure the mapping.
- The index is flat and type-scoped, one `CodexEntryType` per route. There is no
  grouping for pagination to cut across.

## Consult

- `expanded/architecture.md` → Index actions, Must stay unpaginated
- `app/Support/DuplicateName.php`

## Tests

Extend `tests/Feature/ListPaginationTest.php`:

- The index hands a `LengthAwarePaginator` to the view.
- Over one page: page 1 full, page 2 the remainder, with the `tag` filter and
  `search` surviving the page link.
- `DuplicateName::suggest()` runs once per rendered row, not once per project
  row — assert `$duplicateNames` has the page's count, not the total.
- A non-owner gets 403 on `?page=2`.
