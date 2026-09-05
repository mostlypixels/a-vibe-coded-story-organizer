# 01 — Locale foundation

## Scope

- Migration `add_locale_to_users_table`: `string('locale')->nullable()` after `timezone`; add
  `'locale'` to `User::$fillable`.
- `config/locales.php`: `default => 'en'` and a `supported` map of slug → display name, for the
  **24 official EU languages + `en-US`**. Endonym display names. Drop any slug Carbon can't
  localize (verify against Carbon locale data during build).
- `app/Support/LocaleChoice.php`: value object + `resolve(?string): self` (null / stale slug →
  default, never throws) + `all(): array` (picker options). Mirror `ThemePreset`. Fields: slug,
  name, Carbon locale code. **No clock/order.**
- Share the resolved `LocaleChoice` to all views via `AppServiceProvider::boot()` +
  `View::share`, guarded for a guest (guest → default).

Not in scope: any formatting or display change (task 02), the settings UI (03), the picker (04).

## Depends on

- Nothing.

## Key decisions

- Derive-not-map; `LocaleChoice` stays thin. See `00-overview.md` → Binding defaults.
- Config-driven like `config/themes.php`; stale-slug guard like `ThemePreset::resolve()`.

## Consult

- `expanded/data-model.md`, `expanded/architecture.md` → LocaleChoice / sharing.

## Tests

- Unit: `resolve(null)` and `resolve('zz')` → default; `resolve('fr')` → name/Carbon code;
  `all()` keys == `config('locales.supported')`.
- Feature: a request as a logged-in user exposes the shared `LocaleChoice`; a guest gets the
  default without error.
