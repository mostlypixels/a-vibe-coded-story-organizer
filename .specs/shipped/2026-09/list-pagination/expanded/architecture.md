# Architecture

## Resolving the size

`app/Support/PageSize.php`

```php
final class PageSize
{
    /** @return list<int> */
    public static function sizes(): array;
    /** The stored column, or the configured default when null or off the allow-list. */
    public static function resolve(?int $stored): int;
}
```

Same role as `ThemePreset::resolve()` and `FontChoice::resolve()` — the single
door from a stored column to a used value — but **not** a readonly value object
like those two. They carry many fields resolved together; this carries one
integer, and wrapping it would only make callers write `->size`.

Callers write `PageSize::resolve($request->user()?->page_size)` inline. No
`ResolvesPageSize` trait: `ResolvesIndexSorting` exists because the allow-list
check itself was duplicated and forgetting it was an injection hole. Here the
allow-list lives inside `resolve()`, so a caller has nothing to forget.

## Index actions

Nine actions, one edit each: `->get()` → `->paginate(PageSize::resolve(...))->withQueryString()`.

| Controller | Note |
|---|---|
| `SceneController::index` | Joins `chapters`+`acts` with `select('scenes.*')`. Paginate stays after the `orderBy` chain. |
| `ChapterController::index` | `withCount`/`withSum` aliases: still nothing may `select()` after them. |
| `ActController::index` | `withSum('scenes as word_count')`. |
| `BookController::index` | `$wordCounts` must key off `$books->pluck('id')` — now the page's ids, which is what we want. |
| `CodexEntryController::index` | |
| `EventController::index` | |
| `PlotlineController::index` | |
| `TagController::index` | No sorting/filtering today; still paginates. |
| `CodexAttributeController::index` | Ordered by `position`; still paginates. |

`withQueryString()` carries `sort`, `direction`, `search` and the per-list
filters (`chapter`, `act`, `plotline`, `tag`) into the page links.

Filter submits reset to page 1 for free: `x-index-toolbar` is a GET form
carrying only `sort`, `direction` and the filter inputs, so a submit produces a
URL with no `page`. Do not add a `page` hidden field.

## Must stay unpaginated

These are full sets by design, not lists of rows:

- `$destinationActs`, `$destinationChapters`, `$destinationBooks` — move targets
  in the delete-with-move dialog. Moving is not limited to the visible page.
- `chaptersFor($book)`, `actsFor($book)`, the plotline and tag filter selects —
  filter dropdowns. They must offer every option, not the page's.
- `$names` in `SceneController` / `CodexEntryController` — the project-wide name
  list `DuplicateName::suggest()` compares against. It stays one `pluck()`.
- `StoryNumbering::forBook($book)` — book-wide by contract.

The per-row cost the spec calls out fixes itself: `suggest()` is mapped over the
result set, which is now one page. 1600 calls become 100. The `$names` pluck
behind it stays a single query.

## Writing the preference

- Route: `Route::patch('/preferences/page-size', [PageSizeController::class, 'update'])
  ->name('preferences.page-size.update')` in the authenticated group, beside the
  other whole-user preferences. Not under `/admin` — this is set from the list,
  not from Configuration.
- `app/Http/Requests/UpdatePageSizeRequest.php`: `authorize()` returns
  `$this->user() !== null`, mirroring `UpdateAppearanceRequest`. No policy and no
  `ProjectPolicy` walk — the preference has no owning project and the write
  always targets the acting user.
- Rules: `page_size` → `['required', Rule::in(PageSize::sizes())]`;
  `return_to` → `['required', 'string']` plus a same-origin check.
- Controller: `$request->user()->update(['page_size' => ...])`, then redirect to
  `return_to`, falling back to `back()` when it is not local.
- `return_to` is rendered by the bar as `request()->fullUrlWithoutQuery('page')`,
  so a size change lands on page 1 of the same filtered, sorted list. Reject any
  value not starting with `url('/')` — an unchecked redirect target is an open
  redirect.

Rejected: a `?per_page=` query parameter the index action stores on the way past.
It reuses the toolbar form and needs no redirect, but it writes to the database
on a GET. See `open-questions.md`.
