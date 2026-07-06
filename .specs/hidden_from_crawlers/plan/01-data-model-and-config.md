# Task 01 — Data model & config

## Scope

Build the foundation the rest of the feature reads:

- Migration `database/migrations/2026_07_06_000000_create_crawler_settings_table.php`
  — singleton table: `id`, `enabled` boolean **default true**, `user_agent_whitelist`
  json nullable, timestamps. No FKs, no index (single row).
- `App\Models\CrawlerSetting`:
  - `$fillable = ['enabled', 'user_agent_whitelist']`.
  - casts: `enabled => boolean`, `user_agent_whitelist => array`.
  - `static current(): self` — `firstOr(fn () => static::create([...]))` using
    `config('crawlers.default_enabled')` + `[]` whitelist. **Not memoised.**
  - `isHidden(): bool` → `enabled`.
  - `whitelistTerms(): array<int,string>` → trimmed, blank-dropped list.
- `config/crawlers.php` — `default_enabled` (`(bool) env('CRAWLERS_HIDDEN_DEFAULT', true)`)
  and `disallow_path` (`'/'`).

## Explicitly NOT in this task

- No robots.txt generation or route (task 02).
- No meta component (task 03).
- No settings controller/Form Request/view/routes/nav (task 04).
- No docs (task 05).

## Depends on

Nothing.

## Key decisions already made (binding)

- Singleton, global, **not** project-scoped — no `project_id`/`user_id`.
- Default hidden lives in both the column default and config (comment why).
- `current()` is not memoised (value can change mid-request; single-row query is
  cheap).

## Consult

`expanded/data-model.md` (schema, model API), `00-overview.md` (invariants).

## Tests (`tests/Feature/CrawlerSettingTest.php`, start the file here)

- `current()` returns a row reflecting the config default (`enabled === true` on a
  fresh DB) and creates exactly one row.
- Calling `current()` repeatedly never creates a second row (assert count === 1).
- `whitelistTerms()` drops blank/whitespace entries and preserves order.
- `enabled`/`user_agent_whitelist` cast round-trips (bool, array).
