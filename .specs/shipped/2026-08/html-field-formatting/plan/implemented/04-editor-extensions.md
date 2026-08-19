# 04 — Editor extensions

Tiptap gains an alignment attribute and a colour mark, registered for HTML fields only.

**Depends on:** 01.

## Scope

- Two local extensions in `resources/js/wysiwyg.js`, beside the existing `Callout`:
  - `TextAlign` — an `Extension` adding a `textAlign` attribute to the node types named by
    `ALIGNABLE_TAGS`. `parseHTML` reads `rt-align-*` off the class; `renderHTML` writes it
    back. Default `left` renders no class. Commands: `setTextAlign({ align })`.
  - `TextColor` — a `Mark` rendering `['span', { class: 'rt-color-' + name }, 0]`, parsing
    `span[class*="rt-color-"]`, rejecting any name outside the list. Commands:
    `setTextColor({ color })`, `unsetTextColor()`.
- Both pushed into `buildExtensions()` only when `!isMarkdown`.
- `buildSlashItems(format, …)` currently ignores `format`. Give it the gate and append the
  align and colour items for `html` only.
- JS-side `ALIGNMENTS` / `TEXT_COLORS` literals, with the same "keep in step" comment
  `CALLOUT_TYPES` already carries.

**Not in scope:** the toolbar buttons (task 05). Styling (tasks 02–03).

## Key decisions

- **Write both locally; add no npm dependency.** `@tiptap/extension-text-align` and the
  `TextStyle` colour mark emit inline `style`, which the sanitizer strips — the dependency
  would be dead weight that silently does nothing.
- A colour name outside the list must not round-trip. Reject in `parseHTML`, so pasted
  markup cannot smuggle a class the sanitizer would then have to catch.

## Consult

`expanded/architecture.md` → *Editor*, *Value sets in two languages*.

## Tests

In `resources/js/wysiwyg.test.js`, following the `imageOptions` helper already there.

- HTML editor: `setTextAlign('center')` then `getHTML()` yields `class="rt-align-center"`
  and no `style`.
- HTML editor: a colour mark round-trips `getHTML()` → new editor → `getHTML()` unchanged.
- HTML editor: `parseHTML` drops `rt-color-chartreuse`.
- Markdown editor: neither extension is registered
  (`extensionManager.extensions.find(...)` is undefined).
- `buildSlashItems('markdown')` has no align or colour entry; `buildSlashItems('html')` has
  one per registry value.
- Registry parity: the JS literals match the PHP constants, read from source in the manner
  of `css-source-smoke.test.js`.
