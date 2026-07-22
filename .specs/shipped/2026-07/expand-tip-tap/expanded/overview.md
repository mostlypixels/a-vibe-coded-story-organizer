---
title: Expand Tip Tap — Overview
---

# Overview

## Problem statement (expanded)

`resources/js/wysiwyg.js` wires **`StarterKit` only** into the Tiptap editor that backs
both field formats (`html` for the 8 `RichTextFields` fields, `markdown` for
`Scene.contents`). `node_modules/@tiptap` ships no Table, Image, or Task-List
extension. Meanwhile `ValidMarkdown` (`app/Rules/ValidMarkdown.php`) accepts anything
plain CommonMark parses — so `Scene.contents` **can** legitimately hold a Markdown
table, image, or task list today, from three sources that never went through this
editor: a `.zip` project import, a paste from another writing tool, or a scene written
before Tiptap shipped.

When such a scene hydrates into the editor, there is no ProseMirror node to hold the
construct. Verified against `@tiptap/markdown`'s actual source
(`MarkdownManager.parseTokens`/`parseFallbackToken`, not assumed from docs): a Markdown
table is **deleted outright** on hydration — not flattened to plain lines, gone
entirely, headers and all cell text included. Today that destruction requires a
deliberate **Save** click to become permanent. `.specs/draft/autosave-with-revisions`
(handoff.md §11.4) is blocked on this spec because autosave would make the same
destruction happen after two seconds of typing, with no click and no consent.

This spec's job is to close that gap: install the extensions that make the
construct round-trip safely wherever that's a modest lift, and for whatever remains,
give `autosave-with-revisions` a small, exhaustive, structurally-checked list of what
still needs a warning.

## What changed since the spec was drafted

The original "unverified" framing (see `autosave-with-revisions` handoff.md §11.4) has
been replaced by verification against actual package source (pulled tarballs, read
`dist/index.js`, ran constructs through `marked.lexer()` directly). The headline result:
**every construct this spec covers is either already safe or decided-to-support** —
there is nothing left in scope that deletes content outright. See `spec.md`'s
"Synthesis" section for the full reasoning. This reframes the fallback question from
"stop autosave from destroying content" to "surface the few remaining
attribute/structure-level losses that survive install."

## Goals

* Ship editor support for tables, images, and task lists (the three constructs that
  needed a real extension), strikethrough and underline (mark-level fixes), and
  callout/alert blocks (a presentational node over existing blockquote syntax).
* Keep the editor's Markdown-mode output and HTML-mode output each anchored to a single
  round-trip guarantee, pinned by tests — not by prose description.
* Produce the three-item structural-check list `autosave-with-revisions` §11.5.2 needs
  for its fallback warning: a merged table cell, a resized image's dimensions, an HTML
  wrapper tag matching no registered node.
* Keep `RichTextFields::ALLOWED_TAGS` a superset of what the editor can produce (the
  invariant already stated in its docblock).
* Keep the four Markdown-rendering surfaces — editor, `ValidMarkdown`,
  `Scene::renderedContents`, `EpubExporter`'s converter — in agreement on syntax
  (strikethrough today; task lists newly).

## Non-goals

* Autosave behaviour itself (field registry, coalescing, conflict handling) — stays in
  `autosave-with-revisions`.
* Image *upload* and storage. This spec only makes the editor able to *render* an
  existing `<img>`/`![]()` reference. No upload endpoint, no `project_media` table — that
  stays deferred to v2 per `documentation/rich-text.md`'s existing note, owned elsewhere
  by `CodexMediaService`/`CodexMediaRules` when it's picked up.
* Redesigning the editor UI or toolbar layout beyond adding the new entries this spec
  requires. Broader UI rework is `.specs/draft/editor-interface`.
* Footnotes — split to `.specs/draft/footnote-plugin` (no official
  `@tiptap/extension-footnote` exists; supporting them means a hand-written node with
  custom `parseMarkdown`/`renderMarkdown`, a materially bigger lift than everything else
  here). That spec still has an open use-case question to resolve before it can be
  expanded itself.
* Paste-time and import-time transformation of incoming HTML to strip unmatched wrapper
  tags before they reach the document. Sized as real additional scope in `spec.md`'s
  fallback-policy section and deliberately left undecided here — flagged again in
  `open-questions.md`.
* Preventing every route by which a merged-cell/resized-image attribute can enter the
  document (paste from an external source is a residual gap even after the UI-level
  prevention this spec does ship — see `spec.md`'s "Paste is the residual gap" note).

## User stories

* As a writer whose scene contains a table (imported from an old export, or pasted from
  another tool), opening it in the editor no longer deletes the table — I can see and
  edit it like any other content.
* As a writer, I can insert a table or an image from the slash menu or toolbar, the same
  way I already insert a bulleted list or a blockquote.
* As a writer using underline for in-world emphasis (e.g. a handwritten letter), that
  formatting now survives saving a Markdown-mode scene instead of being silently
  unavailable.
* As a writer, `~~struck-through~~` text now looks struck-through everywhere I see this
  scene rendered — the editor, the Story overview, and an exported EPUB — instead of
  disagreeing between surfaces.
* As a writer, I can mark a `> [!NOTE]` blockquote and see it rendered as a styled
  callout box, not a plain quote.
* As the developer picking up `autosave-with-revisions` next, I have an exhaustive,
  three-item, structurally-checked list of what autosave still needs to warn about,
  instead of an open question.

## Acceptance criteria

* `@tiptap/extension-table` (+`TableRow`/`TableHeader`/`TableCell`) and
  `@tiptap/extension-image` are installed and wired into both editor modes; a table and
  an image, once created, survive a full round-trip (hydrate → edit → serialize →
  re-hydrate) in both `html` and `markdown` format, pinned by a test.
* Task lists use the `TaskItem`/`TaskList` nodes from the **already-installed**
  `@tiptap/extension-list` package (not the separate `@tiptap/extension-task-item`/
  `-task-list` shim packages) and round-trip in both formats.
* `RichTextFields::ALLOWED_TAGS` includes `table`/`tr`/`th`/`td` (+`thead`/`tbody` if
  emitted), `img`, and whatever `TaskItem`/`TaskList` render, while remaining a superset
  of the slash menu's output (the existing invariant, now re-verified for the new nodes).
* `Underline` is enabled in Markdown mode via a custom `renderMarkdown`/`parseMarkdown`
  pair emitting/reading literal `<u>text</u>`; it is no longer `mdHide` in
  `resources/js/wysiwyg.js`'s slash-menu list or the toolbar's markdown-mode filter.
* `Strike` is enabled in Markdown mode; `ValidMarkdown` and `EpubExporter`'s converter
  both gain GFM strikethrough support so all four Markdown-rendering surfaces agree.
* A callout/alert Tiptap node recognizes `> [!TYPE]` and renders a styled box in both
  modes; its Markdown serialization round-trips back to `> [!TYPE]` + content.
* Merging table cells and resizing images are disabled in the toolbar/UI for
  Markdown-format fields (the `isMarkdown` conditional already used for
  strike/underline), so a writer cannot create the un-representable case from inside the
  editor.
* A documented, testable, three-check list exists (merged table cell present; image
  `width`/`height` attrs present; HTML block whose outer tag matches no registered
  node's `parseHTML`) that `autosave-with-revisions` can build its warning on.
* `documentation/rich-text.md` is updated: the new allow-listed tags, the corrected
  editor-routing claim for `Scene.contents` (see `spec.md`'s "Loose end spotted" note),
  and the underline/strike mode change.
