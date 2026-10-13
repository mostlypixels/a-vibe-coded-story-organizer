# Architecture

Copies the `page_size` pattern (one integer, allow-list in config), not the `FontChoice`
slug pattern. A percentage is already its own label, so slugs add nothing.

## Data

- Migration: `users.page_width` — `unsignedTinyInteger`, nullable, after `page_size`. Null = default.
- `App\Models\User`: add `page_width` to `$fillable` and cast it to `integer`.
- No seeder change. Existing users get null, so they get the default.

## Config — `config/page_width.php` (new, shaped like `config/pagination.php`)

```php
return [
    'steps' => [50, 60, 70, 80, 90, 100],
    'default' => 70,   // see open-questions.md
];
```

A config test asserts `default` is in `steps`, and `steps` is ascending (the − / + order).

## `App\Support\PageWidth` (new, mirrors `app/Support/PageSize.php`)

- `steps(): list<int>`
- `resolve(?int $stored): int` — stored value, or `default` when null or not in `steps`.
- Labels: `ScaleLabel::format($value, 'width')` — 100 % → "Full width" (grill decision).
- `css(int $step): string` — `"70%"`. The one place that turns a step into a CSS value.

## Rendering

- `resources/views/components/theme-style.blade.php`: append
  `:root{--page-width:<css>}` from `PageWidth::resolve(auth()->user()?->page_width)`.
  Keep it unlayered (see the warning in `app/Services/FontStyleBlock.php`).
  Guests get the default; guest layouts do not use the variable.
- `resources/views/layouts/app.blade.php:41`: `max-w-7xl` → `md:max-w-(--page-width)`.
  The padding (`px-4 sm:px-6 lg:px-8`) stays inside the width.
- Do not put this in `FontStyleBlock`: it is not a font value.

## Save and preview

- `UpdateAppearanceRequest`: `'page_width' => ['nullable', 'integer', Rule::in(PageWidth::steps())]`.
- `AppearanceController@update` is unchanged; `validated()` carries the new field.
- `AppearancePreviewMap::build()`: add `'page_width' => [step => PageWidth::css(step)]`.
- `resources/js/font-preview.js`: add `page_width: '--page-width'` to `PREVIEW_PROPERTIES`.
- Authorization: none new. The field is the acting user's own column, like the font fields.

## Pitfall — integer keys

PHP turns the step keys into integers. JSON object keys come back as strings, but
`steps` (a JSON array) and `active` stay integers. `neighbourSlug()` uses `indexOf`, so
`active.page_width` must be an integer, not `"70"`. Cast in the switcher controller; test it in JS.

## Docs

- `documentation/interface/fonts.md` → add a short "Page width" section, or a new
  `documentation/interface/page-width.md` linked from `documentation/interface/README.md`.
  Say why: percent of window, independent of text size.
