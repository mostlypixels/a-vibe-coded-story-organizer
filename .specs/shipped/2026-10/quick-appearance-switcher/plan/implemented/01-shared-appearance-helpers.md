---
title: "Task 01 — Shared appearance helpers"
---

# Task 01 — Shared appearance helpers

## Scope

Move three pieces of Blade `@php` logic into support code. The panel in task 04 is the second
caller. Pure refactor: rendered HTML stays the same.

- `App\Support\AppearancePreviewMap::build(ThemeStyleBlock $themeStyle): array`. Replaces the
  `$previewMap` block in `resources/views/admin/appearance/edit.blade.php`. The controller passes
  it to the view.
- `ThemePreset::swatch(): array` with keys `stripes` (list), `plate`, `plateBackground`,
  `content` (each `?string`). Replaces the `@php` block in
  `resources/views/components/theme-card.blade.php`.
- `App\Support\ScaleLabel::format(string $value, string $format): string`. Replaces the
  `$tickLabel` closure in `resources/views/components/setting-track.blade.php`.

Does **not** add the panel or `x-theme-swatch`. Task 04 owns them.

## Depends on

Nothing.

## Key decisions already made

- `swatch()` keeps the `Oklch::CSS_VALUE_PATTERN` check on every value. A value that fails is
  dropped, as today.
- `AppearancePreviewMap` theme entries come from `ThemeStyleBlock::declarations()`, not from the
  config.
- Formats for `ScaleLabel`: `px`, `times`, anything else returns the value unchanged.

Detail: `expanded/architecture.md` → *Shared logic*.

## Tests

- Unit `AppearancePreviewMapTest`: keys are the seven fields; theme entries equal
  `declarations()`; font entries equal the config stacks.
- Unit `ThemePresetTest`: `swatch()` drops a value that fails the pattern; returns three stripes
  for a complete preset.
- Unit `ScaleLabelTest`: `100%`+`px` → `16px`; `115%`+`times` → `1.15×`; `1`+`times` → `1×`;
  `1.25`+`times` → `1.25×`.
- Existing `AppearanceSettingsTest` preview-map and picker tests stay green unchanged.
