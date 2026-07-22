# Expand Tip Tap — Plan overview

Widens the Tiptap editor (`resources/js/wysiwyg.js`) and its supporting PHP surfaces
(`RichTextFields`, `ValidMarkdown`, `EpubExporter`) to round-trip tables, images, task
lists, underline, strikethrough, and callout blocks — closing the blocker on
`.specs/draft/autosave-with-revisions` (handoff.md §11.4). See `../expanded/overview.md`
for the full problem statement and acceptance criteria, and `../expanded/architecture.md`
/ `ui.md` / `testing.md` for per-concern detail. Grill record: this plan folds in every
decision from the `plan-tasks` grilling session (dated 2026-07-22); nothing below
re-litigates them.

## Execution order

1. **`01-backend-allow-list.md`** — widen `RichTextFields::ALLOWED_TAGS` and add
   `ALLOWED_ATTRIBUTES`, covering both HTML and Markdown surfaces alike.
2. **`02-backend-converter-consistency.md`** — switch `ValidMarkdown` to GFM, add
   Strikethrough/TaskList extensions to `EpubExporter`'s isolated converter.
3. **`03-extension-wiring.md`** — install and configure `@tiptap/extension-table` /
   `@tiptap/extension-image` / the already-installed `TaskItem`/`TaskList`; introduce
   vitest; pin round-trip behavior with content-level tests.
4. **`04-table-image-ui.md`** — toolbar + slash-menu entries for tables/images/task
   lists, including HTML-mode-only resize and merge/split.
5. **`05-underline-strike.md`** — enable Underline (custom `<u>` passthrough handler)
   and Strikethrough in Markdown mode; remove the `mdHide`/`isMarkdown` suppression.
6. **`06-callout-node.md`** — new custom Tiptap node for `> [!TYPE]` callout blocks,
   both formats.
7. **`07-fallback-warning-list.md`** — standalone module + tests implementing the
   three structural checks (merged table cell, resized image, unmatched HTML wrapper
   tag) that `autosave-with-revisions` §11.5.2 will consume.
8. **`08-docs-and-regression.md`** — `documentation/rich-text.md` updates, CLAUDE.md's
   `npm run test` command addition, import-path regression coverage, final
   `WysiwygFormTest` confirmation pass.

## Binding decisions (do not re-litigate)

- **Both field formats get the same allow-list.** Tables, images, task lists, and
  callouts all apply to the 8 HTML `RichTextFields` fields *and* `Scene.contents`
  (Markdown mode) — one shared allow-list, no per-format fork.
- **Attribute mechanism**: a new `RichTextFields::ALLOWED_ATTRIBUTES` constant
  (`class-string tag => list<attribute>`), separate from `ALLOWED_TAGS`. Do not fold tag
  and attribute lists into one structure.
- **Image resize and table merge/split ship in v1, HTML-mode fields only.** Both are
  lossless there; both stay off (no toolbar affordance, no `resize` option) for
  Markdown-mode fields — the existing `isMarkdown` conditional pattern already used for
  strike/underline.
- **No new npm package for task lists.** `@tiptap/extension-list` is already installed
  and already exports `TaskItem`/`TaskList` with full Markdown handlers. Do not install
  the separate `@tiptap/extension-task-item`/`-task-list` shim packages.
- **Vitest**: new devDependency, `npm run test` script, tests co-located as
  `resources/js/<name>.test.js` next to the source file they cover.
- **The fallback-warning list is a standalone module**, independently testable, not
  folded into the tables/images tasks — it's the deliverable `autosave-with-revisions`
  depends on next.
- **Paste-time / import-time HTML-wrapper transformation is out of scope** for this
  plan entirely. Do not add a task for it.
- **Callout scope**: recognizes GitHub's `[!NOTE]`/`[!TIP]`/`[!IMPORTANT]`/`[!WARNING]`/
  `[!CAUTION]` convention on a blockquote's first line, in both formats.

## Core invariants every task must preserve

- **`RichTextFields::ALLOWED_TAGS`/`ALLOWED_ATTRIBUTES` must stay a superset of what the
  editor's toolbar *and* slash menu can produce** (the existing docblock invariant,
  `documentation/rich-text.md`'s stated rule). Any task adding a new toolbar/slash entry
  must widen the allow-list in the same task, not a later one.
- **`Scene.contents` never touches `HtmlSanitizer`.** It stays gated by `ValidMarkdown` +
  `Str::markdown()` only. Nothing in this plan routes it through the sanitizer.
- **The editor never stores the Tiptap `Editor` instance in Alpine reactive state**
  (see `documentation/rich-text.md`'s CAUTION callout on this — a past incident). Any
  new Alpine methods added to `resources/js/wysiwyg.js` must read the existing
  non-reactive closure variable, not introduce a new reactive one.
- **`EpubExporter`'s converter stays isolated from `Scene::renderedContents()`** — add
  matching extensions to both independently; never make EPUB share the converter
  instance, per `EpubExporter`'s own docblock rationale.
- **Authorization/controller/route surfaces are untouched.** This entire feature is
  editor configuration + allow-list/converter widening; no task should add a controller
  action, route, or policy check.
- **Parallel-test safety**: `composer test` runs via paratest, one SQLite DB per
  process — no shared state assumptions in any new `RefreshDatabase` test.
