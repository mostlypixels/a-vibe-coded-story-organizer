---
title: "Task 02 — Image lightbox component"
---

# Task 02 — Image lightbox component

## Scope

A behaviour-preserving refactor. Move the inline lightbox out of `codex/partials/fields.blade.php` into `resources/views/components/image-lightbox.blade.php`. The codex edit page uses the component.

- The component owns its Alpine state and opens on the `open-lightbox` window event with `{ url, alt }`.
- Same markup, classes, and close behaviour as today: Escape, scrim click, close button. No new focus trap.
- Its `<img>` carries `data-full-opacity`. Task 04's CSS reads it.
- Edit page: the thumbnail buttons dispatch `open-lightbox`. Remove `lightbox` from the page's `x-data`. Leave `filePreview` and the file-preview dialog alone.

Does **not** add the show-page lightbox (task 03) or any CSS (task 04).

## Depends on

Nothing. Tasks 01 and 02 are independent.

## Key decisions already made

- Window event, not a parent variable. It matches the `open-modal` pattern.
- Not built on `x-modal`: its panel background and clipping change the bare-image look.
- One lightbox instance per page.

Detail: `expanded/architecture.md` → *Lightbox*.

## Tests to add

In the existing codex edit feature test file:

- The edit page renders `x-image-lightbox` once, and its `<img>` carries `data-full-opacity`.
- Each reference-image thumbnail button dispatches `open-lightbox` with that image's url and alt.
- The page no longer declares a `lightbox` variable in its `x-data`.
- Existing authorization tests stay green.

Manual, with `run-imagoldfish`: click a thumbnail on a codex edit page, confirm the lightbox opens, and Escape, the scrim, and the close button each close it.
