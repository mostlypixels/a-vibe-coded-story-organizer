---
status: shipped
shipped: 2026-10-06
planned: 2026-10-06
expanded: 2026-10-05
---

# Dim Images On Dark Themes

Dim images on dark themes, full opacity on hover.

## Problem

On a dark theme, the interface is low contrast but images stay bright. They dazzle the reader.

## Goals

- On dark themes, show images at a lower opacity.
- On hover, show them at 100% opacity.

## Non-goals

- No change on light themes.
- No change to the image files or exports.
- No per-user setting.

## Approach

- Add an `image-opacity` token to every preset in `config/themes.php`, so each preset controls its own dimming. See [themes](../../../documentation/interface/themes.md).
- A preset does not state that it is dark. Light presets set the token to 1.
- Do not derive the opacity from `contrast_ceiling`. That value is a test limit, not a render input.

Values:

| Preset | `image-opacity` |
|---|---|
| `daylight` | 1 |
| `dusk` | 1 (light grey, not dark) |
| `low-glare-dark` | 0.8 |
| `no-halation` | 0.65 |

The dark values are starting points. Tune them by eye.

## Decisions

- **Which images:** all content images: covers, list thumbnails, codex gallery, editor images. One shared rule.
- **Excluded:** theme preview cards (they show the real palette) and the lightbox (the explicit "show me" view). Both stay at full opacity.
- **Keyboard focus:** `:focus-visible` on the image link or button restores full opacity, the same as hover.
- **Touch:** no new interaction. Tap opens the lightbox at full opacity. Covers and thumbnails without a lightbox stay dimmed on touch.

## Open questions

None.
