# Overview

## Problem (expanded)

`resources/views/components/wysiwyg.blade.php` renders one flat `role="toolbar"` div containing,
in HTML mode, ~25 `<button>` elements: 4 heading levels, 4 text-format toggles, 5 list/block
toggles, 4 "insert" actions (link, hr, table, image), 1 callout toggle, and 6 table-structure
actions (2 add/remove pairs + merge/split). The only visual grouping is
`<span class="mx-1 h-5 w-px bg-gray-300">` divider ticks, currently placed after: heading levels,
the text-format+list+block group, and the link+hr pair. Table row/column ops and merge/split have
no divider separating them from Image/Callout, and — being only meaningful with the cursor inside
a table — visually claim the same weight as always-applicable buttons.

This is a **pure presentation refactor** confirmed against `resources/js/wysiwyg.js`: `cmd()`,
`isOn()`, `setLink()`, `setImage()`, `toggleCallout()`, and `buildExtensions()` are all called
by name/string from the Blade template and take no toolbar-layout-dependent arguments — none of
that JS needs to change.

## Goals (confirmed against the code)

1. Re-group the existing 25 buttons into labeled clusters with consistent separators:
   **Headings** (H1–H4) → **Text format** (Bold/Italic/Underline/Strike) → **Lists & blocks**
   (bullet/ordered/task list, blockquote, inline code, code block) → **Insert** (link, horizontal
   rule, table, image, callout) → **Table structure** (add/delete row, add/delete column,
   merge/split — HTML-mode only, per the existing `@if (! $markdown)` gate).
2. Move the two least-frequently-used, highest-button-count clusters — **Headings** and
   **Table structure** — behind a dropdown, using the existing `<x-dropdown>` component
   (`resources/views/components/dropdown.blade.php`, already used elsewhere in the app for
   compact menus — see `ui.md` for the reuse decision).
3. Every command, click handler, `isOn()` active-state binding, `aria-label`/`title`, and the
   `@if (! $markdown)` merge/split gate carries over unchanged — only the surrounding markup and
   grouping change.
4. Extract one small reusable Blade component for a single toolbar button
   (`x-wysiwyg.toolbar-button`) so a junior developer adding a 26th command edits one array entry,
   not copy-pastes a 6-line `<button>` block.

## Non-goals (unchanged from spec.md)

- No new editor commands/extensions/round-trip behavior.
- No change to the slash (`/`) menu.
- No contextual enable/disable (greying out table ops outside a table) — out of scope, flagged in
  `spec.md` as a separate future concern.

## Acceptance criteria

- [ ] Every button present today (by command name) is present after the refactor, with the same
      `@click` handler, same `isOn()` binding where applicable, and same `title`/`aria-label` text.
- [ ] Merge/split cell buttons still render only when `! $markdown` (same gate, same location in
      the component tree — inside the Table structure dropdown).
- [ ] The toolbar's outer container keeps `role="toolbar"` and `aria-label="{{ __('Formatting') }}"`.
- [ ] Heading and Table-structure dropdowns are keyboard-operable (open on click, close on
      `@click.outside` / `Escape`, per `<x-dropdown>`'s existing behavior) and each trigger button
      has an `aria-label`/`title` describing the group (e.g. "Heading", "Table").
- [ ] `resources/js/wysiwyg.test.js` and `WysiwygFormTest` (Feature test) continue to pass
      unmodified — confirmed neither currently asserts on toolbar button count, order, or DOM
      structure (only on editor mount/sync behavior), so this refactor doesn't require touching
      either test file. New coverage is additive (see `ui.md`'s testing note).
