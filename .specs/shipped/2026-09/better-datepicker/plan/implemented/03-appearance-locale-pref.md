# 03 — Appearance locale preference

## Scope

- Add a **Locale** section to `resources/views/admin/appearance/edit.blade.php`: a `<select>`
  over `LocaleChoice::all()`, marking the active slug, posting `locale` to the existing
  appearance update route. Mirror the theme/font pickers.
- A live sample date under the select (`DateFormat::dateTime(now, chosen)`) to show the effect,
  like the theme preview.
- `AppearanceController::edit`: pass `LocaleChoice::all()` and the active slug.
- `UpdateAppearanceRequest::rules()`: add
  `'locale' => ['nullable', Rule::in(array_keys(config('locales.supported')))]`. No controller
  `update` change — it already saves `$request->validated()` wholesale.

Not in scope: LocaleChoice/column (01), DateFormat (02, but this task uses it for the preview).

## Depends on

- 01 (column + `LocaleChoice::all`). Uses `DateFormat` from 02 for the preview — order 03 after
  02, or stub the preview and let 02 wire it. Prefer after 02.

## Key decisions

- No policy — appearance writes to the acting user only, like the rest of that form.

## Consult

- `expanded/architecture.md` → Preference plumbing; `expanded/ui.md` → Appearance form.

## Tests

- Feature: owner sets `locale` to a supported slug → persists; unsupported slug → 422;
  `locale = null` → default, no error. Extend the existing appearance test.
