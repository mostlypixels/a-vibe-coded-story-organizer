---
title: "Task 04 — Switcher panel endpoint"
---

# Task 04 — Switcher panel endpoint

## Scope

- Route `GET /admin/appearance/switcher`, name `admin.appearance.switcher`, in the existing
  `admin.` group in `routes/web.php`.
- `App\Http\Controllers\AppearanceSwitcherController` (invokable). Returns the partial with no
  layout.
- View `resources/views/appearance/switcher.blade.php`. Static markup and the
  `x-data="appearanceSwitcher(...)"` config. Layout per `expanded/ui.md` → *Panel partial*.
- Component `x-theme-swatch` (anonymous Blade), built on `ThemePreset::swatch()`.

Does **not** add JavaScript. Task 05 registers `appearanceSwitcher`. Until then the partial is
inert markup. Does **not** add the nav button. Task 06 owns it.

## Depends on

01.

## Key decisions already made

- Config passed to the view (`Js::from`):

  ```
  url        => route('admin.appearance.update')
  previewMap => AppearancePreviewMap::build(...)
  steps      => ['ui_scale' => [...slugs], 'manuscript_scale' => [...], 'ui_leading' => [...], 'manuscript_leading' => [...]]
  labels     => same keys, slug => ScaleLabel::format(value, 'px' | 'times')
  active     => the seven field slugs from ThemePreset::resolve() / FontChoice::resolve()
  messages   => ['failed' => __('Could not save. Try again.')]
  ```

  `ui_leading` and `manuscript_leading` both use the `leading` slug list.
- Theme swatches are radios `name="theme_slug"`; name in `x-tooltip` and an `sr-only` span.
- Font `<select>`s list every family; each `<option>` has `style="font-family: {stack}"`. No
  accessible-font mark.
- Step rows: `−` / label / `+`, `type="button"`, an `aria-label` per row. Label `aria-live="polite"`.
- Last row: "More settings" link to `admin.appearance.edit`.
- No locale, no reset button.

Detail: `expanded/ui.md` → *Panel partial*, *`x-theme-swatch`*, *Font select*, *Size buttons*;
`expanded/architecture.md` → *Controllers*, *Authorization*.

## Tests

New `tests/Feature/AppearanceSwitcherTest.php`:

- Guest → login redirect.
- Signed-in → 200; no `<html>`, no `<nav>`.
- Every preset is a swatch; the active one is checked. `null` columns → defaults checked.
- Every family is in both selects; the active ones are selected.
- Config carries all four step lists in config order.
- A stored slug no longer in config falls back; no exception.
- No locale select, no reset control.
