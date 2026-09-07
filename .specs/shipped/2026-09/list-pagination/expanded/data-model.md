# Data model

## Migration

`database/migrations/2026_09_XX_000000_add_page_size_to_users_table.php`

```
$table->unsignedSmallInteger('page_size')->nullable()->after('ui_leading');
```

Nullable means "never chosen" — the same convention as `theme_slug`, `ui_font`
and `locale`. `PageSize::resolve()` turns null into the configured default, so no
backfill and no default on the column.

`User::$fillable` gains `page_size`. It is a per-user preference written only
through the acting user's own request, like the other appearance columns — not
like `active_project_id`, which is deliberately kept out of `$fillable`.

## Config

New `config/pagination.php` — its own file, beside `search.php` and
`revisions.php`, because those two own their unrelated `per_page` values and
must not start reading this one.

```php
return [
    'default' => 100,
    'sizes' => [50, 100, 250, 500],
];
```

## Not stored

- No per-list size. One column, every list.
- No installation-wide override. `default` is a code default, not a setting.
- Current page is never stored. It lives in `?page=` only.

## Seeding

None. The Melusine seed creates users through `UserFactory`; a null `page_size`
is a valid user.
