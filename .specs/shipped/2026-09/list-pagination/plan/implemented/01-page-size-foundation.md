# 01 — Page size foundation

## Scope

- `config/pagination.php`: `default => 100`, `sizes => [50, 100, 250, 500]`. Its
  own file — `search.php` and `revisions.php` own unrelated `per_page` values and
  must not start reading this one.
- `app/Support/PageSize.php` with `sizes(): array` and `resolve(?int $stored): int`.
- Migration adding `users.page_size` (nullable `unsignedSmallInteger`, after
  `ui_leading`). Copy the reasoning comment style of the `theme_slug` /
  `ui_leading` migrations: nullable means "never chosen", no default on the
  column, no backfill.
- `page_size` into `User::$fillable`, beside the other appearance columns.

## Not in scope

- No route, no controller, no Form Request — task 02.
- No index action changes — tasks 03-05.
- No `ResolvesPageSize` trait. The allow-list lives inside `resolve()`, so a
  caller has nothing to forget. See `expanded/architecture.md` → Resolving the size.

## Key decisions

- `PageSize` is a plain final class with static methods, **not** a readonly value
  object like `ThemePreset` / `FontChoice`. It carries one integer; wrapping it
  would only make callers write `->size`.
- Callers write `PageSize::resolve($request->user()?->page_size)` inline.
- `page_size` is mass-assignable — it is written only through the acting user's
  own request, unlike `active_project_id`.

## Consult

- `expanded/data-model.md`
- `expanded/architecture.md` → Resolving the size

## Tests

- `tests/Unit/PageSizeTest.php`: `resolve()` for null, each allowed size, an
  unlisted integer, and 0.
- Migration test following the existing `Add*ToUsersTable` pattern: column
  exists, is nullable, existing rows are null after migrating.
