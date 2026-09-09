# 02 — `SearchScope` and its factory

## Scope

- `App\Support\SearchScope`: readonly, holding `?int $bookId`, `array $chapterIds`,
  `array<SearchDomain> $domains`, plus the raw `?int $fromChapterId` / `?int $toChapterId`
  the form needs to re-select its own options.
- Methods: `includes(SearchDomain)`, `hiddenByBook(SearchDomain)`, `isNarrowed()`,
  `toQuery()`.
- A factory that turns a validated request plus a project into a `SearchScope`: resolves
  the range to chapter ids and swaps a reversed one.
- Not in scope: validation (task 03), any use of the scope (tasks 04+).

## Depends on

01.

## Key decisions

- **The object runs no query.** It receives the resolved chapter id list. This is what lets
  `tests/Unit/SearchScopeTest.php` be a real unit test.
- The factory slices `Book::chaptersInStoryOrder()` from the `from` chapter to the `to`
  chapter, inclusive. Either bound may be absent, meaning start or end of book.
- **A reversed range is swapped, not rejected.** She picked two chapters; the order she
  clicked them in is not information.
- **Whole-book range short-circuits**: when the slice is every chapter in the book, leave
  `chapterIds` empty. The book filter alone then does the work and no `whereIn` is built.
- `includes()` is false when the domain is unchecked **or** `hiddenByBook()` is true.
  `hiddenByBook()` is true only when a book is set and `SearchDomain::carriesBook()` is
  false. Keeping them apart is what lets the view explain itself.
- `toQuery()` omits null, empty and default values, so a bare search has a clean URL.
- Lives in `app/Support` beside `SearchResults` — a value object, not a workflow.

## Consult

`expanded/architecture.md` → *`App\Support\SearchScope`*, *Chapter range, in story order*.

## Tests

`tests/Unit/SearchScopeTest.php` for the object, a feature test for the factory's lookup.

- A range crossing an act boundary yields every chapter between, in story order.
- A reversed range is swapped.
- A range covering the whole book leaves `chapterIds` empty.
- `includes()` is true for every domain when `domains` is empty.
- `hiddenByBook()` is true for Plotlines, Events and the codex only when a book is set.
- `toQuery()` omits empty keys.
