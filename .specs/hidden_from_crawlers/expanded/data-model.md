# Hidden from crawlers — Data model

## New table: `crawler_settings` (singleton)

A single application-wide row (one website → one robots policy). No `project_id`,
no `user_id` — it is global, unlike every other domain table which hangs off
`Project`.

Migration `2026_07_06_000000_create_crawler_settings_table.php`:

| Column                 | Type              | Notes                                                        |
|------------------------|-------------------|--------------------------------------------------------------|
| `id`                   | `id()`            |                                                              |
| `enabled`              | `boolean` default `true` | Hidden mode toggle. Default `true` = hidden (spec).   |
| `user_agent_whitelist` | `json` nullable   | Array of user-agent substring terms allowed while hidden.    |
| `timestamps`           |                   |                                                              |

No index needed (single row). No foreign keys.

> [!NOTE]
> The default lives in **two** places by design: the DB column default (`true`)
> and `config('crawlers.default_enabled')`, used when `current()` lazily creates
> the row. Keep them equal. The config value is the documented source of truth
> ("the default is hidden"); the column default is a backstop for direct inserts.

## New model: `App\Models\CrawlerSetting`

- `$fillable = ['enabled', 'user_agent_whitelist']`
- Casts: `enabled => boolean`, `user_agent_whitelist => array`
- `static current(): self` — returns the singleton, lazily creating it from
  `config('crawlers.default_enabled')` + empty whitelist via
  `firstOr(fn () => static::create([...]))`. **Not memoised** (value can change
  within a request lifecycle — e.g. settings update then robots fetch in one
  test; the single-row query is trivial).
- `isHidden(): bool` — returns `enabled`.
- `whitelistTerms(): array<int,string>` — cleaned list (trim + drop blanks) so
  callers never emit an empty/whitespace `User-agent:` line.

This is a genuine singleton, not tied to `Project`, so **it is deliberately
outside `ProjectPolicy`'s authorization walk** — see `architecture.md`.

## New config: `config/crawlers.php`

```php
return [
    'default_enabled' => (bool) env('CRAWLERS_HIDDEN_DEFAULT', true),
    'disallow_path'   => '/',
];
```

- `default_enabled` — seeds the first `current()` read (spec: default hidden).
- `disallow_path` — the path after `Disallow:` for the catch-all block. Keeps the
  robots.txt shape as configuration, honouring the guideline "avoid magic
  strings" and "configuration in a single place".

## Seeding impact

- `DatabaseSeeder` runs with `WithoutModelEvents`, but `CrawlerSetting` has **no
  `booted()` hooks**, so no seeding caveat applies — `current()` self-heals on
  first read. Seeding a row is optional; if `MelusineSeeder` wants a deterministic
  state it may `CrawlerSetting::current()` (or `firstOrCreate`) explicitly. Not
  required for the feature.

## Whitelist storage shape

Stored as a JSON array of plain strings, e.g. `["Googlebot", "Bingbot"]`. Edited
in the UI as a textarea (one term per line); the Form Request splits/normalises
lines into the array (see `architecture.md`). Terms are validated line-safe (no
newline, `:`, or `#`) so they cannot corrupt the generated robots.txt.
