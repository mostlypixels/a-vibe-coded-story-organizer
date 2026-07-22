---
title: Expand Tip Tap — UI
---

# UI

Two surfaces to update: the slash menu (`buildSlashItems()` in `resources/js/wysiwyg.js`)
and the always-visible toolbar (`resources/views/components/wysiwyg.blade.php`). Both
must stay in sync per `documentation/rich-text.md`'s stated invariant — "whatever the
toolbar *or* slash menu can produce must survive `HtmlSanitizer` unchanged."

## Slash menu (`buildSlashItems()`)

Add entries following the existing pattern (`title`, `keywords`, `run`, optional
`mdHide`):

```js
{ title: 'Table', keywords: ['table', 'grid'], run: ({ editor, range }) =>
    at(editor, range).insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run() },
{ title: 'Image', keywords: ['image', 'img', 'picture'], run: ({ editor, range }) => {
    at(editor, range).run();
    onImage(); // new callback, mirrors onLink — prompts for a URL/alt text
} },
{ title: 'Task list', keywords: ['todo', 'checklist', 'checkbox'], run: ({ editor, range }) =>
    at(editor, range).toggleTaskList().run() },
{ title: 'Callout', keywords: ['note', 'tip', 'warning', 'alert', 'callout'], run: ({ editor, range }) =>
    at(editor, range).setCallout({ type: 'note' }).run() }, // exact command name depends on the custom node's addCommands()
```

* `Underline`'s existing entry (`{ title: 'Underline', ..., mdHide: true, ... }`) drops
  `mdHide: true` — it's the one that stops being suppressed.
* `Strikethrough`'s existing entry drops `mdHide: true` for the same reason.
* No merge/split-cell slash entry is added for either format — merging is a
  post-insertion table operation done via toolbar buttons (see below), not something a
  slash command inserts fresh.
* Image insertion needs a prompt, same shape as `setLink()`'s `window.prompt` — a second
  `onImage` callback into the Alpine component, or extend `setLink`'s pattern with a
  sibling `setImage()` method that prompts for a URL (and optionally alt text) and calls
  `editor.chain().focus().setImage({ src, alt }).run()`.

## Toolbar (`wysiwyg.blade.php`)

Current structure: heading buttons (H1–H4) → simple `$toggles` array (Bold/Italic
[/Underline/Strike if `! $markdown`]/lists/blockquote/code) → link button → horizontal
rule button.

1. **Underline moves out of the `! $markdown` guard.** Today:
   ```php
   if (! $markdown) {
       $toggles[] = ['U', 'toggleUnderline', 'underline', __('Underline')];
       $toggles[] = ['S', 'toggleStrike', 'strike', __('Strikethrough')];
   }
   ```
   Both entries become unconditional — push them into the base `$toggles` array
   alongside Bold/Italic, since both marks now round-trip in every format. This removes
   the only `$markdown`-conditional branch in the toggle list.

2. **New toggle-style entries** for Task list (`toggleTaskList` / `taskList` active-name)
   go in the `$toggles` array next to the existing Bulleted/Numbered list entries —
   same button shape, no special handling needed since it's a plain toggle command.

3. **Table and Image need their own buttons**, not `$toggles` entries — inserting a
   table takes arguments (rows/cols) and inserting an image needs a prompt, matching how
   the Link and Horizontal-rule buttons are already handled separately below the
   `$toggles` loop rather than folded into it. Add two buttons in that same section:
   ```blade
   <button type="button" @click="cmd('insertTable', { rows: 3, cols: 3, withHeaderRow: true })" ...>{{-- table icon --}}</button>
   <button type="button" @click="setImage()" ...>{{-- image icon --}}</button>
   ```

4. **Callout button.** Same "own button" tier as Table/Image (takes a `type` argument —
   at minimum a way to pick which of the five types, e.g. a small dropdown or a
   cycling button; exact interaction pattern is an implementation detail left open, not
   specified here since it's presentational, not round-trip-affecting).

5. **Merge/split-cell buttons — Markdown-format fields only suppress them.** Once a
   table exists, `mergeCells`/`splitCell` need their own toolbar affordance (likely
   contextual — only visible/enabled with a cell selection, same idea as how other rich
   editors gate table-only commands). Per `spec.md`'s prevention decision: **do not
   render this affordance at all when `$markdown` is true.** This is the concrete
   `isMarkdown` conditional `architecture.md` refers to — new code, since no merge/split
   UI exists yet (StarterKit ships no table today), not a change to an existing button.

6. **Resize handles on images** — the `Image` extension's own `resize` option, left
   `false`/unset for `$markdown` fields (see `architecture.md`). No toolbar button is
   involved either way; this is purely an extension-config difference, listed here only
   so the UI-layer prevention story is complete in one place.

## Icons

The existing toolbar uses text/HTML-entity glyphs (`B`, `I`, `&bull;`, `&rdquo;`,
`&lt;/&gt;`, `{ }`, `&#128279;` for link, `&mdash;` for hr) rather than an icon font or
SVG set — matches CLAUDE.md's "reuse existing Tailwind components before creating new
ones," so new buttons should follow the same glyph-as-label convention rather than
introducing an icon dependency: e.g. `⊞`/`▦` for table, a simple `🖼` or `[img]` for
image, `☑` for task list, `!` or a per-type letter for callout. Exact glyph choice is
cosmetic and can be decided at implementation time; the pattern (inline glyph, no new
asset) is the constraint worth stating here.

## CSS (`resources/css/app.css`)

The `.tiptap` block (lines ~229–280) is kept "minimal so it doesn't fight the Tailwind
`prose` classes" per its existing comment. New node types need at least:
* Table borders/cell padding inside `.tiptap` (ProseMirror's default table CSS is bare;
  `prose` from `@tailwindcss/typography` does style tables reasonably, but confirm it
  covers the ProseMirror-specific `.ProseMirror-selectednode`/column-resize-handle
  affordances if resize/merge UI ships).
* Task-list checkbox styling — `TaskItem`'s default `renderHTML` typically emits
  `<li data-checked><label><input type="checkbox">...`; needs `list-style: none` on the
  `<ul data-type="taskList">` wrapper and basic checkbox alignment, same treatment
  `.tiptap` already gives placeholder/slash-menu styling.
* Callout box styling — border colour + icon per `[!TYPE]`, mirroring the GFM alert
  callout look already used in this repo's own `documentation/*.md` files (per
  CLAUDE.md's own convention) rather than inventing a new visual language.

## `documentation/rich-text.md` updates

Two required edits, both flagged in `spec.md`:
* The allow-list table (currently listing 19 tags) grows to include the new tags from
  `architecture.md`.
* The "Underline and Strike are **disabled** in this mode" sentence under "Two modes" is
  now wrong for Underline (and for Strike, which stops being disabled too) — rewrite to
  describe the new behavior.
* The "Loose end" `spec.md` spotted: `RichTextFields`' docblock claim that
  `Scene.contents` is *"never routed through the sanitizer **or the editor**"* is stale
  — `scenes/edit.blade.php` already renders it with `<x-wysiwyg … markdown>`. Fix the
  docblock (drop "or the editor," the sanitizer half stays true) while touching this
  file for the other reasons above.
