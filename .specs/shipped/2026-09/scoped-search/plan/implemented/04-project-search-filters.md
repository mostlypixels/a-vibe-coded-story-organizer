# 04 — `ProjectSearch` applies the scope

## Scope

- `search()` and `searchDomain()` take a `SearchScope`, defaulting to `new SearchScope()`.
- `search()` puts `collect()` in a skipped domain's slot **without running its query**.
- `queryFor()` applies book, chapter range and codex type.
- Not in scope: anything HTTP (task 05).

## Depends on

02.

## Key decisions

- **The default scope changes nothing.** Every existing caller and test keeps working
  untouched. That is the point of the default argument.
- Book → `where('acts.book_id', …)` on Acts, Chapters and Scenes. Those joins already
  exist in `queryFor()`. Acts joins `books` today through `whereHas`; give it the same
  treatment without changing its shape more than the filter needs.
- Chapter range → `whereIn('chapters.id', …)` on Chapters and Scenes, and
  `whereIn('acts.id', …)` on Acts, from the acts those chapters belong to. An empty
  `chapterIds` applies nothing — see the whole-book short-circuit in task 02.
- **Codex is one query for three domains.** It runs when any of the three is included, and
  carries `whereIn('type', …)` for exactly those. Never hydrate a type nobody asked for.
- Plotlines and Events are never filtered by book or range. `SearchDomain::carriesBook()`
  already says which domains those are — read it, do not write a second list.
- `booksById()` still loads every book. It feeds display, not filtering. Leave it.

## Consult

`expanded/architecture.md` → *`App\Services\ProjectSearch`*.
`expanded/overview.md` → *Where the filtering must happen*.

## Tests

Extend `tests/Feature/ProjectSearchTest.php`. Fixtures need two books, two acts each, and
chapters whose `position` values are gappy and whose names sort against story order.

- Book filter narrows Acts, Chapters and Scenes to that book.
- Book filter leaves Plotlines, Events and codex rows **untouched** — the hiding is the
  view's job. Assert that split explicitly; it is the part a later reader gets backwards.
- Chapter range narrows Scenes to those chapters' scenes, and Acts to their acts.
- A range crossing an act boundary keeps the chapters between.
- An excluded domain runs no query — count queries, since an empty collection would also
  pass if the query ran.
- Checking Characters alone loads no locations or organizations.
- With a book filter the Scenes query returns only that book's scenes. Assert the row
  count: not hydrating the other book's `contents` is the whole feature.
- Every existing unfiltered assertion still passes.
