---
status: shipped
shipped: 2026-10-13
planned: 2026-10-11
expanded: 2026-10-11
---

# Container Width

The page container is `max-w-7xl` in `layouts/app.blade.php`, a `rem` value. The interface
text size sets `:root { font-size }`, so a larger text size also widens the page. The writer
cannot have large text in a narrow column, or small text across a wide screen.

Scene prose has `max-w-none`, so the manuscript column follows the container too.

## Goals

- A width setting that does not change with the text size.
- Choices: full width, plus a ramp of window percentages (several degrees of "narrow").
- One setting for the app page container.
- Live preview and save, like the theme and font settings: the Appearance page and the quick
  appearance switcher.

## Non-goals

- Modal, popover, dropdown and lightbox widths.
- Form and card widths inside a page (`max-w-xl` and similar). They stay as they are for now.
- Guest, error and public share pages.
- EPUB and static-site exports.
- A free-form width value. Choices come from configuration, like `config/fonts.php`.

## Rough approach

- Follow the font-choice pattern: config list of steps, a nullable slug per user, resolve
  with a fallback, write the value into the existing style block.
- Replace the layout's `max-w-7xl` with a CSS variable the style block sets.
- Add the setting to the Appearance page and the quick switcher.

## Open questions

- Should the manuscript (scene editor and scene view) get its own width, or follow the page?
- How many steps, and which percentages?
- On a phone, does a narrow step still apply, or is every step full width below a breakpoint?
- Default step: full width, or near today's 80rem look?
- Should the sidebar or the page chrome stay full width while only the content narrows?
