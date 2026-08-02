# 04 — Per-user theme preference and picker

## Scope

- Migration `add_theme_slug_to_users_table` — `string`, **nullable**, no default.
- `User::$fillable` gains `theme_slug`.
- `<x-theme-style />` resolves `auth()->user()?->theme_slug ?? config('themes.default')`.
- `AppearanceController::update()` + `PATCH /admin/appearance` → `admin.appearance.update`.
- `app/Http/Requests/UpdateThemeSettingRequest`.
- `resources/views/admin/appearance/edit.blade.php` — replace the placeholder with the picker.

Does **not** include: fonts, sizing, per-token editing, live preview, or a contrast readout —
all `display-configurator` (spec 3).

## Depends on

02, 03 (a picker offering one option is untestable).

## Key decisions already made

- **Nullable, no default value.** `null` means "follow `config('themes.default')`", so changing
  the default still reaches users who never picked. Do not copy the default into the column.
- `rules()`: `theme_slug` → `['nullable', Rule::in(array_keys(config('themes.presets')))]`.
  Never a free string reaching the renderer.
- `authorize()`: `$this->user() !== null`, matching `UpdateCrawlerSettingRequest` /
  `UpdateImportSettingRequest`. The update writes to `$request->user()`, so there is no
  cross-user case. **No `ProjectPolicy` walk** — this is owned by no `Project`.
- The picker is a `<fieldset>` of native radios, not a `<select>` and not styled `<div>`s —
  arrow-key navigation comes free and no Alpine is needed.
- Swatches are `aria-hidden` decoration; each radio's text label carries the preset name,
  translated with `__()`. A color strip is not an accessible label.
- Swatch inline `style="background-color: …"` is unavoidable (values are data, not classes) and
  must pass the same validation `ThemeStyleBlock` uses.
- Apply on submit. No live preview — that is spec 3, where the machinery is worth it.
- Two comments currently claim this page is finished: `admin/appearance/edit.blade.php`'s
  *"Final v1 content… no later task enriches this page"* and `AppearanceController`'s docblock
  naming spec 3 as the enricher. **Both change here.**

## Consult

`expanded/ui.md` → *Preset picker*; `expanded/architecture.md` → *HTTP layer*.

## Tests

`tests/Feature/AppearanceSettingsTest`:
- Guest → redirected to login on both routes.
- Authenticated user sees every preset, active one marked.
- `PATCH` with a valid slug updates **that user** and redirects with the status flash.
- `PATCH` with a slug not in config → `assertSessionHasErrors('theme_slug')`.
- `PATCH` with `null` clears the preference back to the default.
- Two users hold different preferences simultaneously and each renders their own.
- A stored slug that no longer exists in config falls back to the default rather than throwing —
  a config edit must not white-screen every page.
