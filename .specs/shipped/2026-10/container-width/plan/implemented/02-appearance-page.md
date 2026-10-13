# 02 — Appearance page

## Scope

- `ScaleLabel`: new `width` format; `100%` → `__('Full width')`, else the value.
- `AppearanceController@edit`: pass the width options (`step => PageWidth::css(step)`) and the resolved step.
- `admin/appearance/edit.blade.php`: "Layout" fieldset after the fonts, one `x-setting-track` named `page_width`, `format="width"`, `selected` cast to int (`(int) old('page_width', …)`).
- `AppearancePreviewMap::build()`: `page_width` entry.
- `resources/js/font-preview.js`: `page_width: '--page-width'` in `PREVIEW_PROPERTIES`.
- Deferred: switcher row → 03.

## Depends on

- 01.

## Key decisions already made

- No sample box; the page is the preview.
- Reuse `x-setting-track` unchanged unless integer keys break it.

## Docs

- `expanded/ui.md` → Appearance page; `expanded/architecture.md` → Save and preview.

## Tests

- `ScaleLabelTest`: `width` format for 70 % and 100 %.
- `AppearancePreviewMapTest`: `page_width` keyed by every step.
- `AppearanceSettingsTest`: edit page shows the track with the saved step checked; after a failed validation the old step stays checked.
- JS: `resolvePreview(maps, 'page_width', 70)` → `{ '--page-width': '70%' }`; unknown step → null.
