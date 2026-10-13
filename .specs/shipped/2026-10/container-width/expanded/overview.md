# Overview

## Decisions

- Steps: **50, 60, 70, 80, 90, 100 %** of the window. 100 % is labelled "Full width".
- The writer moves one step at a time with − / + (10 % per step). There is no free value.
- The width is a percentage of the viewport, so the text size does not change it.
- Below the `md` breakpoint every step renders full width. 50 % of a phone is too narrow to read.
- The manuscript follows the page container (`max-w-none` prose stays as it is).
- Only the `<main>` content narrows. The navigation and breadcrumb bars stay full width.

## User stories

- As a writer with large text, I want a narrow column, so my lines stay short.
- As a writer on a wide screen, I want the page to fill the window, so small text uses the space.
- As a writer, I want to see the width change before I save it.

## Acceptance criteria

- Appearance page and quick switcher both show a "Page width" control with − / +.
- − is disabled at 50 %; + is disabled at Full width.
- A change previews at once. The switcher saves it like the other appearance fields.
- Changing the text size does not change the container width in pixels.
- A stored value that is not a configured step falls back to the default.
- Guest, error and public share pages keep their own widths.
