# Testing

Extend `tests/Feature/ProjectSearchTest.php` (service) and `tests/Feature/SearchTest.php`
(HTTP). Add `tests/Unit/SearchScopeTest.php`.

Fixtures need two books, each with two acts, each act with chapters whose `position`
values are gappy and whose names sort differently from their story order. Without that
shape none of the ordering bugs can fail.

## `SearchScope`

- Range resolution crosses an act boundary: from chapter 2 of act 1 to chapter 1 of act 2
  yields every chapter between, in story order.
- A reversed range is swapped, not rejected.
- `includes()` returns true for every domain when `domains` is empty.
- `toQuery()` omits null and empty keys, so a bare search has a clean URL.

## Service

- Book filter narrows Acts, Chapters and Scenes to that book.
- Book filter leaves Plotlines, Events and codex rows untouched — the *hiding* is the
  view's job, not the service's. Assert that split explicitly; it is the part a later
  reader will get backwards.
- Chapter range narrows Scenes to scenes of those chapters, and Acts to their acts.
- An excluded domain runs **no query**: wrap in `DB::listen` or `assertQueryCount`, since
  an empty collection alone would also pass if the query ran.
- Existing unfiltered assertions still pass with the default scope.

## HTTP

- Filters round-trip through the URL: response contains them, back button state works.
- `See all :count` counts the filtered set, and its href carries book, range and domains.
- The domain page honours the same filters and keeps them across `page=2`.
- Requesting a domain the scope excludes redirects to the index.
- Book from another project → 422. Chapter from another book → 422.
- `from_chapter` without `book` → 422.
- Single-book project renders no book control.
- Multi-book project with a book chosen renders no Timeline or Codex section, and shows
  the explanatory line.

## Performance

- With a book filter, the Scenes query returns only that book's scenes — assert row count,
  since the whole feature is about not hydrating `Scene.contents` for the other book.
