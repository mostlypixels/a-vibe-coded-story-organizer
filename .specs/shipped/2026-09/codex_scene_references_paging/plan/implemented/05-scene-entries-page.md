# 05 — The scene→entries full page

## Scope

- Route `GET /scenes/{scene}/codex-references`, name `scenes.codex-references.index`,
  shallow, beside the existing shallow scene routes.
- `SceneController::codexReferences()`: authorize, then `->paginate()` on the
  `codexReferences()` relation.
- `resources/views/components/references/entry-row.blade.php` — entry name linking
  `codex.show`, type label. No cover.
- `resources/views/references/entries.blade.php` — full page, same shape as task 03's.
- Not in scope: capping the scene cards (task 06).

## Depends on

02.

## Key decisions

- Real `->paginate(PageSize::resolve($request->user()?->page_size))`. The order is SQL
  `(type, name)`, so the query can page honestly. Do not hand-build a paginator where the
  query can do it — that would load every referenced entry to show one page.
- This asymmetry with task 03 is deliberate. Comment the action saying why, or the next
  reader makes the two match.
- Back link targets `scenes.show`.
- Task 02 left `forScene()` returning a `Collection`. Decide here whether to add a
  query-returning sibling on `ReferencingScenes` or paginate the relation in the
  controller — keep the eager-load list in one place either way.

## Consult

`expanded/architecture.md` → *Routes*, *Controller actions*. `expanded/ui.md` → *Full pages*.

## Tests

Extend `tests/Feature/ReferenceListTest.php`.

- Page 1 and page 2 of a 3-page set return disjoint rows; `page=2` round-trips.
- Rows per page follows the user's `page_size`.
- Rows are ordered `(type, name)`.
- Empty state renders when the scene references nothing.
- Non-owner → 403.
