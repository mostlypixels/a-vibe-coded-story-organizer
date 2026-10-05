---
title: Dim Images On Dark Themes — Plan Overview
---

# Plan Overview

Manual. Never itself implemented or moved to `plan/implemented/`.

## Execution order

| # | Task | Purpose |
|---|------|---------|
| 01 | `01-image-opacity-setting.md` | `image_opacity` per preset, `ThemePreset::$imageOpacity`, `--image-opacity` in the style block and the picker preview. No visible change yet. |
| 02 | `02-image-lightbox-component.md` | Extract `x-image-lightbox`, opened by a window event. The edit page uses it. Lightbox image gets `data-full-opacity`. |
| 03 | `03-show-page-lightbox.md` | Codex show page: the main gallery image opens the lightbox. Depends on 02. |
| 04 | `04-dim-images-css-and-docs.md` | The CSS rule that dims images, themes docs, CHANGELOG, tune values by eye. Depends on 01 to 03. |

The CSS rule comes last so no image dims before the lightbox opt-out and the touch path exist.

## Binding design decisions (do not re-litigate)

Resolved in the grill; recorded in `../resolution-log.md`.

1. **A scalar preset key, not a `ThemeTokens` entry.** `image_opacity` in `config/themes.php`, `--image-opacity` in CSS. The colour vocabulary, pairs, whitelist, and token tests stay untouched.
2. **Values:** `daylight` 1.0, `dusk` 1.0, `low-glare-dark` 0.8, `no-halation` 0.65. Starting points, tuned by eye in task 04.
3. **Hover on the image, or on its enclosing link or button, restores full opacity.** So does `:focus-visible` on that link or button.
4. **Dimming is on by default.** The default preset is `low-glare-dark`, so logged-out pages dim too.
5. **All content images dim** through one global `img` rule. The lightbox image opts out with `data-full-opacity`. Theme preview cards are not `<img>`.
6. **The codex show page gets a lightbox.** The main image becomes a button that opens the current image. Thumbnails still switch the main image.
7. **One shared `x-image-lightbox` component.** It owns its state and opens on the `open-lightbox` window event with `{ url, alt }`. Same look and behaviour as today. Not built on `x-modal`.
8. **No transition** on the opacity change.

## Core invariants every task preserves

- **`ThemeStyleBlock` prints unescaped.** `--image-opacity` comes only from a validated float within 0 to 1, never from a raw config string.
- **One source for the live preview and the page.** `ThemeStyleBlock::declarations()` feeds both. `resources/js/font-preview.js` needs no change.
- **The colour token tests stay as they are.** Do not add `image-opacity` to `ThemeTokens::ALL`.
- **The edit-page lightbox keeps its current look and close behaviour** (Escape, scrim click, close button).
- **Authorization on the codex pages is unchanged.** Keep the existing non-owner 403 tests green.
- **Exports do not change.** EPUB and static-site templates never read presets.
