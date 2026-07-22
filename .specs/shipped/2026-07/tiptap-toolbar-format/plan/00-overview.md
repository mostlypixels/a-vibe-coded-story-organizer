# Tiptap Toolbar Format — plan overview

Pure presentation refactor of `resources/views/components/wysiwyg.blade.php`'s toolbar. No
changes to `resources/js/wysiwyg.js`, no new commands, no DB/model changes. See
`../expanded/overview.md` and `../expanded/ui.md` for the full design; this file is the binding
execution order and the invariants every task must preserve.

## Execution order

1. **`01-toolbar-button-component.md`** — extract `x-wysiwyg.toolbar-button`, a reusable
   sub-component for the plain `cmd()`/`isOn()` toggle-button shape. Convert every existing button
   that fits that shape (headings, text-format, lists/blocks, table row/column ops, merge/split)
   to use it, in-place — same flat layout, same divider positions as today. No dropdowns yet.
2. **`02-cluster-restructure-dropdowns.md`** — regroup into the 5 named clusters and wrap
   **Headings** and **Table structure** in `<x-dropdown>`. Depends on 01 (needs the component to
   populate dropdown contents cleanly). Adds the regression test.

## Binding design decisions (do not re-litigate)

- Reuse the existing `<x-dropdown>` component (`resources/views/components/dropdown.blade.php`)
  for both collapsed clusters — no new dropdown component.
- `x-wysiwyg.toolbar-button` covers **only** the `cmd(command[, args])` + optional
  `isOn(active[, args])` shape. Link, Image, and Callout call no-arg helper functions
  (`setLink()`, `setImage()`, `toggleCallout()`) and stay hand-written — do not force them into
  the shared component.
- Cluster order: Headings (dropdown) → Text format → Lists & blocks → Insert (link, hr, table,
  image, callout) → Table structure (dropdown, merge/split still gated on `! $markdown`).
- Divider tick style (`<span class="mx-1 h-5 w-px bg-gray-300"></span>`) is unchanged and placed
  consistently between every cluster, including before/after each dropdown trigger.
- No contextual enable/disable of table-only buttons outside a table — explicitly out of scope.
- No auto-opening the Table structure dropdown when the cursor enters a table — same reason.

## Invariants every task must preserve

- Every command present today (by name) is present after each task, with the same `@click`
  handler and the same `isOn()` binding where applicable.
- Merge/split cell buttons render only when `! $markdown` — same gate, wherever they end up living.
- The toolbar's outer container keeps `role="toolbar"` and `aria-label="{{ __('Formatting') }}"`.
- `resources/js/wysiwyg.test.js` and `tests/Feature/WysiwygFormTest.php` continue to pass
  unmodified after each task (neither currently asserts on toolbar structure).
- `npm run test`, `composer test`, and `composer lint` all pass at the end of each task.

## Open questions left for implementation-time judgment (non-blocking)

From `../expanded/open-questions.md`:
- Dropdown trigger glyph for Headings when no level is active — task 02 recommends a plain `H`,
  implementer may adjust for visual clarity as long as active-state highlighting still works.
- Whether a chevron/caret affix on dropdown triggers is wanted — left to the implementer's
  judgment; not load-bearing for functionality.
