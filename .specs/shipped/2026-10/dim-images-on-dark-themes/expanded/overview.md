# Dim images on dark themes — overview

## Why

- `low-glare-dark` (ceiling 10.0) and `no-halation` (ceiling 3.8) cut text glare on purpose. Full-brightness images undo that.
- Only these two presets are dark. `daylight` and `dusk` are light.
- The default preset is `low-glare-dark`. New users and logged-out pages (guest, public, welcome, error layouts) get dimming from day one.

## User stories

- On `no-halation`, I open a codex gallery and the images do not dazzle me. I hover one, or tab to it, and see it at full brightness.
- On `daylight`, I notice nothing.

## Image sites in scope

- Covers: `projects/index`, `projects/edit`, `books/edit`, `chapters/show`, `chapters/edit`, `codex/index`, `codex/show`, `codex/partials/fields`.
- List thumbnails: `components/recent-list`.
- Codex gallery: `codex/show` (main image and thumbnails), `codex/partials/fields` (edit grid).
- Editor and rich-text `<img>`: `img` is in `RichTextFields::ALLOWED_TAGS`.

## Out of scope

- The lightbox image: stays full opacity.
- Theme preview cards: they use a CSS background plate, not `<img>`, so a rule on `img` does not reach them.
- EPUB and static-site exports use their own templates and never read presets.

## Added in planning

- The codex show page had no lightbox, so touch users could not see its gallery at full brightness. This feature adds one. The spec did not list it. See [architecture](architecture.md) → Lightbox.
- Covers and thumbnails elsewhere still have no full-brightness path on touch. Accepted.

## Acceptance criteria

- `daylight` and `dusk`: every `<img>` computes to opacity 1.
- `low-glare-dark` computes to 0.8 and `no-halation` to 0.65, on every in-scope image.
- Hover on the image, or on its enclosing link or button, gives opacity 1.
- `:focus-visible` on the enclosing link or button gives opacity 1.
- The lightbox image is always opacity 1.
- Codex show page: the main gallery image is a button. Click or tap opens the current image in the lightbox. Thumbnails still switch the main image.
- Codex edit page: the lightbox works as before.
- On the Appearance page, picking a preset changes image opacity before save. Saving keeps it.
- A preset with a missing or out-of-range value renders full opacity, never broken CSS.
