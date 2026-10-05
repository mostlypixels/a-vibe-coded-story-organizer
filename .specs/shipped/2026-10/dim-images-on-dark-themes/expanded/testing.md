# Dim images on dark themes — testing

PHP only. No JS test: `font-preview.js` is unchanged. CSS has no PHP test, so the hover and focus rules get a manual check.

## Unit

`ThemePresetTest`:

- Every configured preset sets `image_opacity` explicitly, as a number from 0 to 1.
- `daylight` and `dusk` are 1.
- `low-glare-dark` and `no-halation` are below 1.
- `no-halation` dims at least as much as `low-glare-dark`. Guard the order, not the exact numbers, so tuning stays cheap.
- `fromSlug()` returns 1.0 when a preset omits the key.

`ThemeStyleBlockTest`:

- `render()` includes `--image-opacity:0.8;` for a 0.8 preset and `--image-opacity:1;` for 1.0.
- `declarations()` includes `--image-opacity`. This is the array the picker reads.
- A value below 0 or above 1 emits no `--image-opacity` entry.

## Feature

- `ThemeRenderingTest`: a page rendered for each preset carries that preset's `--image-opacity`. Add it beside `assertEmitsEveryToken`.
- `AppearanceSettingsTest`: the preview map holds `--image-opacity` for every preset slug.
- Codex edit page: renders `x-image-lightbox` once. The lightbox `<img>` carries `data-full-opacity`. Thumbnail buttons dispatch `open-lightbox`.
- Codex show page: with a gallery, renders the lightbox once and a main-image button that dispatches `open-lightbox`. Without a gallery, no lightbox.
- Both pages keep their existing authorization tests.

## Manual

Use the `run-imagoldfish` skill (Playwright, not Chrome). Check `no-halation`, `low-glare-dark`, and `daylight`:

- Codex show: main image and thumbnails dim. Hover restores. Clicking the main image opens the lightbox at full brightness. Escape closes it.
- Codex edit: gallery grid dims. Tab to a thumbnail button restores. Opening the lightbox shows full brightness.
- Projects index: cover restores when the pointer is over the card link.
- Appearance page: selecting a preset changes image opacity before save.
- Tune 0.8 and 0.65 by eye. Change the config and note the new values in `resolution-log.md`.
