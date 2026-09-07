# Testing

Plain PHPUnit, `RefreshDatabase`, factories, `actingAs()`, named routes.

## New: `tests/Feature/ListPaginationTest.php`

- Each of the nine indexes returns a `LengthAwarePaginator` in its view data.
  One data-provider test over `[route name, factory, parent]` beats nine copies.
- Seed 3 rows: the bar still renders, `total()` is 3.
- Seed 0 rows: the bar renders, no error.
- Seed 120 scenes at the default size: page 1 has 100, page 2 has 20.
- `?page=2&sort=name&direction=desc&search=x` — the page-2 link keeps all four.
- Story numbering: 120 scenes, page 2, first row's `#` is 101 — asserts
  `StoryNumbering` still counts from the book.
- Move buttons: page 2's first row is not disabled; page 1's is.
- A non-owner gets 403 on `?page=2` exactly as on page 1 (authorization runs
  before pagination).

## New: `tests/Feature/PageSizePreferenceTest.php`

- `PATCH preferences.page-size.update` with 250 stores 250 and redirects to
  `return_to`.
- 999 (off the allow-list) fails validation; the column is unchanged.
- A `return_to` on another host redirects `back()` instead — the open-redirect guard.
- `return_to` carrying `?page=12` lands the redirect on page 1.
- Guest is redirected to login.
- User A's write does not touch user B's column.
- Set 250 on the scenes list, then load the events list: 250 there too.

## New: `tests/Unit/PageSizeTest.php`

`PageSize::resolve()` for null, each allowed size, an unlisted integer, and 0.

## Existing tests to update

`SceneTest`, `ChapterTest`, `ActTest`, `BookTest`, `EventTest`,
`PlotlineTest`, `CodexEntryTest`, `CodexAttributeTest`, `TagTest` — any
assertion reading the view's collection as an Eloquent `Collection`, or counting
rows in the response, now meets a paginator. Grep for `assertViewHas` on those
keys before editing.

## Migration test

`AddPageSizeToUsersMigrationTest` — follows the existing
`Add*ToUsersTable`/migration-test pattern: column exists, is nullable, existing
rows are null after migrating.

## Not tested here

Search and revision-history paging keep their own tests unchanged. If either
starts failing, this feature has leaked into their `per_page`.
