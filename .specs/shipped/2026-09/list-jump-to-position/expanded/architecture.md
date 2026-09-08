# Architecture

No migration, no model change, no new route. The whole feature is two query parameters on
the existing index routes plus one support class.

## What "Go to" means

**Go to always shows the whole book at that group.** It clears the search, clears the
filter, and forces `sort=position&direction=asc`. One rule, no modes — the writer never
has to work out which state the toolbar was in.

Settled in the plan grill. It is what removes the filtered-jump machinery: no direction
handling, no reversed id list, no "target is empty under this search" branch, no flash
message.

## The two parameters

| Parameter | Meaning | Lifetime |
| --- | --- | --- |
| `jump=1` | "The chapter in the select is a destination, not a filter" | Consumed by `index()`, never rendered back |
| `highlight=<id>` | "Mark these rows" | Survives paging via `withQueryString()` |

The destination id rides in the existing `chapter` (or `act`) select — one dropdown serves
both buttons. `jump` is the submit button's own name/value, so which button was pressed is
what distinguishes a filter from a jump.

`jump` is transient. `index()` resolves it and **redirects** to the same route with `page`,
`highlight` and a `#`-fragment. The landed URL is then an ordinary paginated URL:
shareable, bookmarkable, and the back button steps out of the jump instead of re-running
it. Today's filter URLs are untouched, so existing bookmarks and `ListPaginationTest`
keep working.

## The fragment

The redirect ends in `#chapter-<id>` (`#act-<id>` on the chapter list), and that group's
**first row on the page** carries the matching `id` attribute. The browser scrolls there
itself.

Without it a group whose first row is row 100 of the page lands off the bottom of the
screen — the feature would put you on the right page and still make you hunt. No
JavaScript, and no `scroll-margin` either: no table in this app has a sticky header.

## `App\Support\ListJump`

New, beside `PageSize` — one static, no state, no dependencies. Its job is the two things
that are easy to get wrong: splitting the ordered id list at the target, and the
off-by-one.

```php
/**
 * The 1-based page holding the first row of $targetId's group.
 *
 * @param  list<int>  $orderedIds  Group ids in story order.
 */
public static function page(
    Builder $rows,       // the book's unfiltered row query
    string $column,      // 'scenes.chapter_id' | 'chapters.act_id'
    array $orderedIds,
    int $targetId,
    int $perPage,
): int
```

One count query, no hydration: rows in the groups *preceding* the target, then
`intdiv($before, $perPage) + 1`. A target absent from `$orderedIds` is the caller's problem
— see the concern below.

**Why not a row-value comparison** on `(acts.position, acts.id, chapters.position, …)`?
It hard-codes the story-order tuple into the support class for no gain. The ordered id list
is already in memory — `chaptersFor()` / `actsFor()` fetch it for the dropdown regardless.

## `JumpsToListPosition` concern

`app/Http/Controllers/Concerns/JumpsToListPosition.php`, following `ResolvesIndexSorting`'s
shape: it resolves, the caller acts.

```php
/**
 * The redirect a Go-to request should answer with, or null when this is not one.
 */
protected function jumpRedirect(
    Request $request,
    string $route,                // 'books.scenes.index' | 'books.chapters.index'
    Book $book,
    Builder $rows,
    string $column,
    EloquentCollection $groups,   // story-ordered, from chaptersFor()/actsFor()
    int $perPage,
    string $fragmentPrefix,       // 'chapter' | 'act'
): ?RedirectResponse
```

It:

1. returns `null` unless `jump` is filled;
2. reads the destination from the select's own parameter (`chapter` / `act`);
3. **rejects an id absent from `$groups`** — the same allow-list discipline
   `ResolvesIndexSorting` applies to `sort`. `$groups` is already scoped to the book, so
   this is also the parameter's authorization boundary: a foreign id is treated as absent
   and falls through to a plain page 1, leaking nothing. Returns a redirect to the bare
   index rather than null, so `jump` never survives;
4. builds the target URL with **only** `page`, `highlight`, `sort=position`,
   `direction=asc` — search, filter and `jump` are all dropped by construction, not by
   subtraction;
5. appends `#<fragmentPrefix>-<id>`.

## Controller changes

Both `index()` methods, immediately after `authorize()` and before any query building:

- `$perPage = PageSize::resolve($request->user()?->page_size);` — extracted from the
  inline `paginate()` argument, because the jump needs the same number.
- the `jumpRedirect(...)` call, returning early when it answers non-null. Early enough that
  a jump does no filtering, no aggregate and no pagination work at all.

`SceneController::index` passes `'scenes.chapter_id'`, `chaptersFor($book)`,
`$book->sceneQuery()`; `ChapterController::index` passes `'chapters.act_id'`,
`actsFor($book)`, `$book->chapterQuery()`.

Return type widens to `View|RedirectResponse` on both.

## The page-range line

Computed in the controller from the already-hydrated, already-eager-loaded paginator — no
extra query. `SceneController` reads `$scenes->first()->chapter` and `$scenes->last()
->chapter`; `ChapterController` reads `->act`. Null when the page is empty or when
`$sort !== 'position'`, because a name-sorted page covers no contiguous range.

Passed to the view as `$pageRange` (`?string`), rendered by `x-pagination-bar`. See
[ui.md](ui.md).

## Authorization

Unchanged. Both actions already `authorize('view', $book->project)` before anything else,
and the redirect is issued after that check. No new policy, no new gate.

## Conflicts with existing invariants

- **Scene move buttons.** `scenes/index.blade.php` renders up/down only when
  `$sort === 'position' && request()->filled('chapter')`. A jump clears the filter, so a
  jumped-to view has no reorder buttons — deliberate. Reordering crosses chapter boundaries
  in an unfiltered list and `ReordersSiblings` swaps siblings only. **Filter** sits one
  button away from **Go to**, which is the answer.
- **`StoryNumbering`** is built from the whole book, not the filtered set, so the `#` column
  is already correct on any page and needs no change.
- **Position ordering.** `ListJump` is correct only while the ordered id list matches the
  index's own ordering exactly, `id` tie-breaks included. `chaptersFor()` already applies
  the same four keys as `SceneController::index`. If they drift the jump lands a page off,
  silently — [testing.md](testing.md) guards it.
