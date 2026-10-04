---
status: draft
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

- Drive the opacity from a theme token, so each preset controls its own dimming. See [themes](../../../documentation/interface/themes.md).

## Open questions

- Which images: codex gallery only, or every image in the app?
- Does keyboard focus also restore full opacity?
- Does touch, with no hover, need another way to see the full image?
- Does a preset state that it is dark, or is that derived?
