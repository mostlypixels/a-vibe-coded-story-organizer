---
title: "Task 03 — Show-page lightbox"
---

# Task 03 — Show-page lightbox

## Scope

The codex show page gets a way to see its gallery at full brightness, which matters on touch.

- `codex/show.blade.php`: move the main gallery `<img>` inside a `<button type="button">`. Click dispatches `open-lightbox` with the current image's `url` and `alt`.
- Include `<x-image-lightbox />` once on the page, only when the gallery is not empty.
- Use the focus-ring classes of the edit-page thumbnail button.
- Thumbnails keep switching `current`. A one-image gallery gets the button too.

Does **not** change the thumbnail row, any controller, or any CSS.

## Depends on

Task 02.

## Key decisions already made

- The main image opens the lightbox. Thumbnails do not.
- The button's accessible name comes from the image alt text. Add no new translation string unless the existing alt is empty.
- No lightbox navigation (next, previous). It shows one image.

Detail: `expanded/architecture.md` → *Lightbox*.

## Tests to add

In the existing codex show feature test file:

- With a gallery, the page renders `x-image-lightbox` once and a main-image button that dispatches `open-lightbox`.
- With no gallery, the page renders no lightbox.
- Existing authorization tests stay green (non-owner gets 403).

Manual, with `run-imagoldfish`: click the main image, confirm the lightbox shows the current image, switch thumbnails, and open again.
