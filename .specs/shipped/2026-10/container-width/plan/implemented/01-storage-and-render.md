# 01 — Storage and render

## Scope

- Migration `users.page_width` (unsigned tiny int, nullable, after `page_size`); `User` fillable + `integer` cast.
- `config/page_width.php`: `steps`, `default => 70`.
- `App\Support\PageWidth`: `steps()`, `resolve(?int): int`, `css(int): string`.
- `UpdateAppearanceRequest`: `page_width` rule.
- `theme-style.blade.php`: append `:root{--page-width:…}`. `layouts/app.blade.php:41`: `max-w-7xl` → `md:max-w-(--page-width)`.
- Deferred: Appearance control and preview → 02; switcher → 03; docs → 03.

## Depends on

- None.

## Key decisions already made

- Mirrors `app/Support/PageSize.php` and `config/pagination.php`, not `FontChoice` slugs.
- Not part of `FontStyleBlock`. Keep the rule unlayered.

## Docs

- `expanded/architecture.md` → Data, Config, `PageWidth`, Rendering, Save and preview.

## Tests

- `tests/Unit/Support/PageWidthTest.php`: resolve null / each step / 55 / 0 / 110; `css(70)`.
- Config test: default in steps; steps ascending; all within 1–100.
- `AppearanceSettingsTest`: saves 50 (redirect and JSON 204); rejects 55, 45, 110, `"wide"`; null resets; app page renders `--page-width:50%`, default for null and off-list; other appearance columns unchanged.
