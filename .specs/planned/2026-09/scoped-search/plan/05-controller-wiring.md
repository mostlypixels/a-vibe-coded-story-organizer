# 05 — Controller wiring and scoped URLs

## Scope

- Both `SearchController` actions build the scope through the task 02 factory and pass it
  to `ProjectSearch`.
- `index()` also resolves the picker's options: the project's books, and the book's
  chapters **only when a book is resolved**.
- `domain()` redirects to the index when `$scope->hiddenByBook($domain)`, and appends the
  scope to the paginator.
- The "see all" link in `components/search/result-table.blade.php` and the *Back to
  search* link in `search/domain.blade.php` carry `SearchScope::toQuery()`.
- Not in scope: the Narrow panel markup (task 06) or the hidden-section copy (task 07).

## Depends on

03, 04.

## Key decisions

- **The controller stays thin**: it calls the factory and passes the result on. No filter
  logic, no range slicing.
- **`domains[]` is ignored on the domain page.** The URL names the domain; a stale
  checkbox in the same URL does not override it. Only `hiddenByBook()` redirects.
- `$paginator->appends($scope->toQuery() + $request->only('q', 'mode'))`.
- **One query-string builder.** Three literal lists is how these drift — every link goes
  through `toQuery()`.
- A multi-book project with no book chosen runs no chapter query. That is the reason the
  picker options are resolved here and not in the view.

## Consult

`expanded/architecture.md` → *`App\Http\Controllers\SearchController`*.
`expanded/ui.md` → *Links that must carry the scope*.

## Tests

Extend `tests/Feature/SearchTest.php`.

- Filters round-trip through the URL.
- *See all :count* counts the filtered set, and its href carries book, range and domains.
- The domain page honours book and range, and keeps them across `page=2`.
- A book filter plus a project-wide domain page redirects to the index.
- An unchecked domain's own page still renders.
- A multi-book project with no book chosen loads no chapters — count queries.
