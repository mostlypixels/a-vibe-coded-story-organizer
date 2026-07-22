---
status: shipped
shipped: 2026-07-22
planned: 2026-07-22
expanded: 2026-07-22
---

# Tiptap Toolbar Format

## Problem

`resources/views/components/wysiwyg.blade.php`'s toolbar has grown one button at a time across
the `expand-tip-tap` feature (headings, marks, lists, blockquote, code, link, horizontal rule,
table, image, callout, merge/split, and now table row/column add-remove) without ever revisiting
the layout. In HTML-mode fields the row now holds ~25 buttons; it survives today only because
`flex flex-wrap` lets it spill onto a second/third line, which reads as an undifferentiated wall
of glyphs rather than a toolbar a junior developer — or an end user — can scan. There is no
grouping stronger than a handful of `<span class="mx-1 h-5 w-px bg-gray-300">` divider ticks, and
several buttons (table row/column ops, merge/split) are only meaningful with the cursor inside a
table but render identically to always-applicable buttons everywhere else.

## Goals

- Reorganize the existing toolbar's visual grouping (e.g. tighter/more consistent separators,
  grouping table-only operations together, considering an overflow or dropdown affordance for
  less-frequent actions such as headings or table row/column ops) so it reads as organized
  sections rather than one long flex-wrapped row.
- Preserve every existing command, its keyboard/click behavior, and the existing HTML-mode-only
  gating (merge/split, image resize) — this is a presentation change, not a capability change.
- Keep it usable by a junior developer extending it later: whatever grouping mechanism is chosen
  should be a small, reusable pattern (e.g. one Blade partial/component per group), not one-off
  markup duplicated per field.

## Non-goals

- No new editor commands, extensions, or round-trip behavior — everything in
  `resources/js/wysiwyg.js` (extensions, `cmd()`, `buildExtensions()`) stays as-is.
- No change to the slash (`/`) menu — it already exists as the "compact" alternative to the
  toolbar and is out of scope here.
- Not attempting to visually disable/grey out context-inapplicable buttons (e.g. table ops
  outside a table) — that is a separate, harder "toolbar state" concern (contextual
  enabled/disabled rules) and is deliberately not bundled into this reorganization.

## Rough approach

Group the current flat button list into labeled clusters (text formatting, lists/blocks,
insert — link/hr/table/image/callout, table structure — merge/split/row/column) using the
existing separator convention, and evaluate whether the least-used clusters (likely headings and
table structure) belong behind a small dropdown/menu component rather than inline, following
whatever pattern this app already uses elsewhere for compact menus (check
`resources/views/components/` for an existing dropdown to reuse before inventing one). Keep the
`$toggles` array / `$btnBase` Blade-side approach in `wysiwyg.blade.php` as the base to refactor
from, not replace.
