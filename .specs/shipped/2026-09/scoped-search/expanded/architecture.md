# Architecture

## `App\Support\SearchScope`

One readonly value object carries every filter. Without it, four parameters thread through
`search()`, `searchDomain()`, `searchEntityFor()` and `queryFor()`, and the view builds the
same query string in three places.

```php
final readonly class SearchScope
{
    public function __construct(
        public ?int $bookId = null,
        public ?int $fromChapterId = null,
        public ?int $toChapterId = null,
        /** @var array<int, SearchDomain> empty means every domain */
        public array $domains = [],
    ) {}

    public static function fromRequest(SearchRequest $request, Project $project): self;
    public function includes(SearchDomain $domain): bool;   // domain filter + book/domain rule
    public function isNarrowed(): bool;
    public function toQuery(): array;                       // for links and appends()
}
```

`toQuery()` is the single builder for the filter query string — used by
`components/search/result-table.blade.php`, `search/domain.blade.php`, and
`$paginator->appends()`. Three literal lists is how these drift.

Lives in `app/Support` beside `SearchResults` and `SearchResultRow`: a value object, not a
workflow.

## Chapter range, in story order

`chapters.position` is per-act and gappy, so `BETWEEN` is wrong across an act boundary.
Resolve the range to a **chapter id list** instead:

1. Take the book's chapters in `(acts.position, chapters.position, chapters.id)` order.
2. Slice from the `from` chapter to the `to` chapter, inclusive.
3. `whereIn('chapters.id', $ids)` on the Chapters and Scenes queries.
4. Acts narrows to `whereIn('acts.id', $ids->pluck('act_id')->unique())`.

Resolve once per request, in `SearchScope`, not per domain.

A range needs a book, because numbering restarts per book (`StoryNumbering`). So the
control only exists once a book is chosen, or when the project has exactly one.

**Pitfall:** `whereIn` with one bound variable per chapter. A 1000-chapter book approaches
the SQLite variable ceiling. See `open-questions.md`.

## `App\Services\ProjectSearch`

- `search()` and `searchDomain()` take a `SearchScope` (default `new SearchScope()`, so
  existing callers and tests are unchanged).
- `search()` skips a domain when `! $scope->includes($domain)` and puts `collect()` in its
  slot. It must **not** run the query — the point is not running it.
- Codex is one query split three ways, so it runs when any of the three codex domains is
  included, and the split drops the excluded ones.
- `queryFor()` gains the scope and applies:
  - Book → `where('acts.book_id', …)` on Acts, Chapters, Scenes. The joins already exist.
  - Chapter range → the `whereIn` above.
  - Plotlines, Events, Codex — never filtered. `SearchDomain::carriesBook()` already says
    which is which.

`booksById()` still loads every book; it feeds `SearchResultRow::$book` for display, not
filtering. Leave it.

## `App\Http\Requests\SearchRequest`

Add, all `nullable`:

| Key | Rule |
|---|---|
| `book` | `integer`, exists in `books` where `project_id` = the route project |
| `from_chapter`, `to_chapter` | `integer`, exists in `chapters` **and** belongs to the chosen book — check in `withValidator()`, since the book comes from the same request |
| `domains` | `array`; `domains.*` → `Rule::enum(SearchDomain::class)` |

Normalize a reversed range (`to` before `from` in story order) by swapping, not by
erroring. She picked two chapters; the order she picked them in is not information.

A `from_chapter` with no `book` is a validation error, not a silent ignore.

## `App\Http\Controllers\SearchController`

Both actions build the scope and pass it through. `domain()` additionally:

- Redirects to the index when the requested domain is excluded by the scope — the page
  cannot render results it was told not to search.
- `$paginator->appends($scope->toQuery() + $request->only('q', 'mode'))`.

The controller stays thin: no filter logic, only `SearchScope::fromRequest()`.
