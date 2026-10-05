---
title: "Task 01 — Image opacity setting"
---

# Task 01 — Image opacity setting

## Scope

The server half: each preset carries an image opacity, and the style block and picker preview emit it as `--image-opacity`.

- `config/themes.php`: `'image_opacity'` on all four presets, beside `contrast_ceiling`. Add one line to the file's comment block saying what the key does.
- `ThemePreset`: new `float $imageOpacity = 1.0` as the **last** constructor parameter, so `ThemeStyleBlockTest::fixture()` still builds. `fromSlug()` reads `$preset['image_opacity'] ?? 1.0`. Update the class docblock.
- `ThemeStyleBlock::declarations()`: append `--image-opacity` after the colour loop. Update its docblock, because the array is no longer only `--color-<token>`.

Does **not** touch CSS or Blade. Task 04 owns the rule that reads the property.

## Depends on

Nothing.

## Key decisions already made

- Not a `ThemeTokens` entry. Do not touch `ThemeTokens`, `PAIRS`, `DECORATIVE`, or `Oklch::CSS_VALUE_PATTERN`.
- Values: `daylight` 1.0, `dusk` 1.0, `low-glare-dark` 0.8, `no-halation` 0.65.
- Output value: `(string) round($preset->imageOpacity, 2)`, so `1.0` prints `1` and `0.8` prints `0.8`.
- Emit the entry only when the float is within 0 to 1. Otherwise skip it. The CSS fallback in task 04 gives 1.
- `AppearanceController` and `font-preview.js` need no change. Both already read `declarations()`.

Detail: `expanded/architecture.md` → *Opacity is not a colour token*, *Config and value object*, *Rendering*.

## Tests to add

Unit, in `tests/Unit/ThemePresetTest.php`:

- Every configured preset sets `image_opacity` explicitly, as a number from 0 to 1.
- `daylight` and `dusk` are 1. `low-glare-dark` and `no-halation` are below 1.
- `no-halation` is at most `low-glare-dark`. Guard the order, not the numbers.
- `fromSlug()` returns 1.0 when a preset omits the key.

Unit, in `tests/Unit/ThemeStyleBlockTest.php`:

- `render()` includes `--image-opacity:0.8;` for 0.8 and `--image-opacity:1;` for 1.0.
- `declarations()` includes `--image-opacity`.
- A value below 0 or above 1 emits no `--image-opacity` entry.

Feature:

- `tests/Feature/ThemeRenderingTest.php`: a page rendered for each preset carries that preset's `--image-opacity`.
- `tests/Feature/AppearanceSettingsTest.php`: the preview map holds `--image-opacity` for every preset slug.
