# Data model

## `users.locale`

- New migration `add_locale_to_users_table`: `string('locale')->nullable()` after `timezone`.
- Add `'locale'` to `User::$fillable`. No cast (plain string).
- `null` = never chosen → resolves to the app default. Mirrors `theme_slug`.
- Pre-V1 demo data: no backfill; the seeder's admin user keeps `null` (see the reseed note —
  `migrate:fresh --seed` + `app:install-demo`).

## `config/locales.php` (new)

Config, not rows — the supported set changes only when a file changes. Mirrors
`config/themes.php`.

```php
return [
    'default' => 'en',
    'supported' => [
        'en' => ['name' => 'English',   'carbon' => 'en', 'clock' => 12, 'order' => 'mdy'],
        'fr' => ['name' => 'Français',  'carbon' => 'fr', 'clock' => 24, 'order' => 'dmy'],
        'it' => ['name' => 'Italiano',  'carbon' => 'it', 'clock' => 24, 'order' => 'dmy'],
    ],
];
```

- `carbon` — the Carbon/ICU locale used for month names.
- `clock` — `12` or `24`.
- `order` — segment order for the picker and the display pattern: `mdy` | `dmy` | `ymd`.

Explicit `clock`/`order` rather than parsing each locale's ICU pattern: deterministic, and the
list is small. See `open-questions.md`.

## Invariants

- Event `event_datetime` storage format is unchanged (`Y-m-d H:i:s`, the model's datetime cast).
- A stored `locale` no longer in `config('locales.supported')` must resolve to the default, not
  throw — same stale-slug guard as `ThemePreset::resolve()`.
