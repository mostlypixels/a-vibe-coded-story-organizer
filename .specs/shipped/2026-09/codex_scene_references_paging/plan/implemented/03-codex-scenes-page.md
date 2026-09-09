# 03 — The codex→scenes full page

## Scope

- Route `GET /codex/{codexEntry}/scenes`, name `codex.scenes.index`, shallow, beside the
  existing shallow codex routes in `routes/web.php`.
- `CodexEntryController::scenes()`: authorize, take the full sorted collection from
  `ReferencingScenes::forEntry()`, hand-build a `LengthAwarePaginator` over `forPage()`.
- `resources/views/components/references/scene-row.blade.php` — scene name linking
  `scenes.show`, chapter, act, book when `$showBook`, event title + date or `—`.
- `resources/views/references/scenes.blade.php` — the full page, copied from
  `search/domain.blade.php`.
- Not in scope: capping the cards (task 04). The cards keep their current markup until then.

## Depends on

01, 02.

## Key decisions

- Hand-built paginator, not `->paginate()`. The sort runs in PHP, so SQL `LIMIT/OFFSET`
  would page the wrong set. Copy `SearchController::domain()` exactly, including
  `Paginator::resolveCurrentPath()`. Comment the action saying why it differs from the
  scene direction.
- Rows per page is `PageSize::resolve($request->user()?->page_size)`. There is no
  `search.per_page`.
- `$showBook = $entry->project->books()->count() > 1`, resolved in the controller and
  passed to every row. Never derived per row.
- The page keeps timeline order. No sortable column headers.
- Back link targets `codex.show`.
- Page shape: heading, a muted line naming the entry, back link, `x-table`, `x-row-range`
  + `$paginator->links()`, empty state.

## Consult

`expanded/architecture.md` → *Routes*, *Controller actions*. `expanded/ui.md` → *Two row
components*, *Full pages*, *Book name*.

## Tests

New `tests/Feature/ReferenceListTest.php`.

- Page 1 and page 2 of a 3-page set return disjoint rows; `page=2` round-trips.
- Rows per page follows the user's `page_size`, not a constant (mirror
  `PageSizePreferenceTest`).
- Order across a page boundary matches `ReferencingScenes::forEntry()` — the reason the
  collection is paged rather than the query.
- Empty state renders when the entry has no references.
- Non-owner → 403.
- Single-book project prints no book name. Two books, one unnamed: the unnamed one prints
  the project name and `#` appears nowhere.
