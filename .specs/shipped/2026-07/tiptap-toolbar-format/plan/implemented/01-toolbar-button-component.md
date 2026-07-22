# Task 01 — Extract `x-wysiwyg.toolbar-button` component

## Scope

Build the new sub-component and convert every existing button that fits its shape to use it, with
**no visible change** to the toolbar's layout, order, or divider positions.

- Create `resources/views/components/wysiwyg/toolbar-button.blade.php`, used as
  `<x-wysiwyg.toolbar-button>`. Props: `command`, `args` (default `null`), `active` (default
  `null`, a 2-tuple `[activeName, activeArgs]` when present), `label`, `title`. See
  `../expanded/ui.md`'s "New component" section for the exact markup to implement (the
  `@click="cmd(...)"` / `:class="isOn(...)"` binding built from those props, `$attributes->merge`
  for the base classes, `title`/`aria-label` both set from `$title`).
- In `resources/views/components/wysiwyg.blade.php`, replace the current inline `<button>` markup
  with `<x-wysiwyg.toolbar-button>` calls, driven by arrays, for:
  - The 4 heading-level buttons (currently a `@foreach (range(1, 4) as $level)` loop).
  - The existing `$toggles` array in full: Bold, Italic, Underline, Strike, Bulleted list,
    Numbered list, Task list, Blockquote, Inline code, Code block.
  - The 4 table row/column buttons: Add row below (`addRowAfter`), Delete row (`deleteRow`), Add
    column right (`addColumnAfter`), Delete column (`deleteColumn`).
  - The 2 merge/split buttons (`mergeCells`, `splitCell`) — keep them behind the existing
    `@if (! $markdown)` gate, unchanged condition and unchanged position in the markup.
- Preserve today's flat layout exactly: same button order, same divider (`<span class="mx-1 h-5
  w-px bg-gray-300"></span>`) placement as currently in `wysiwyg.blade.php`. This task is a
  markup/component swap only.

### Explicitly not in scope (owned by task 02)

- No dropdowns — Headings and Table structure stay inline, exactly where they are today.
- No cluster regrouping or reordering (e.g. Table/Image do **not** move next to Link/HR yet).
- No divider repositioning.
- Link, Image, and Callout buttons are untouched by either task — they call `setLink()`,
  `setImage()`, `toggleCallout()` (no-arg helpers, not `cmd()`), so they don't fit the new
  component's shape and stay hand-written exactly as they are today.

## Depends on

None — this is the first task.

## Key decisions already made (binding, from `00-overview.md`)

- `x-wysiwyg.toolbar-button` covers only the `cmd(command[, args])` + optional
  `isOn(active[, args])` shape — do not extend it to cover Link/Image/Callout.
- Every command present today must remain present, with the same `@click` handler and the same
  `isOn()` binding where applicable.
- Merge/split cell buttons render only when `! $markdown` — same gate, same place in the tree.
- The toolbar's outer container keeps `role="toolbar"` and `aria-label="{{ __('Formatting') }}"`.

## Docs to consult

- `../expanded/ui.md` — "New component: `x-wysiwyg.toolbar-button`" section has the exact Blade
  code sketch to implement (props, `@click`/`:class` construction, `$attributes->merge` base
  classes).
- `../expanded/overview.md` — acceptance criteria this task must keep satisfying (command parity,
  `isOn()` parity, `role="toolbar"`/`aria-label` on the container).

## Tests

No new tests required and no existing test files change. `tests/Feature/WysiwygFormTest.php` and
`resources/js/wysiwyg.test.js` don't assert on toolbar DOM structure today, so they should pass
unmodified. Verify by running:

- `composer test` — confirms `WysiwygFormTest` still passes (editor mount/hydrate/sync behavior
  unaffected by the button markup swap).
- `npm run test` — confirms `wysiwyg.test.js` still passes.
- `composer lint` — new Blade component file must pass project lint/format.
- Manual/visual check (or `run-imagoldfish` skill): open a page with the WYSIWYG editor in both
  Markdown and HTML mode, confirm every button still renders in the same position, still toggles
  active state correctly, and merge/split still appear only in HTML mode.

The regression test asserting representative `aria-label`s survive the full refactor is added in
task 02, once the final button/cluster layout is in place.
