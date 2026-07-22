# Task 03 — Extension wiring + vitest + round-trip tests

## Scope

Install and configure the editor extensions that give Table, Image, and Task List
constructs real Markdown/HTML round-trip support, and introduce the JS test tier that
pins the behavior. This task is about **capability**, not UI chrome — no toolbar or
slash-menu changes here (that's task 04).

- `npm install` (pinned to `3.27.1`, matching the project's existing `@tiptap/*` pin):
  `@tiptap/extension-table` (+ its `TableRow`/`TableHeader`/`TableCell` exports —
  confirm the exact import shape once installed) and `@tiptap/extension-image`.
- **Do not install** `@tiptap/extension-task-item`/`@tiptap/extension-task-list` — import
  `TaskItem`/`TaskList` from the **already-installed** `@tiptap/extension-list` package
  instead (confirmed present: `node -e "console.log(Object.keys(require('@tiptap/extension-list')))"`
  lists both).
- Add `Table`, `TableRow`, `TableHeader`, `TableCell`, `Image`, `TaskItem`, `TaskList` to
  the shared `extensions` array built in `resources/js/wysiwyg.js`'s `init()`, applied
  unconditionally (both `html` and `markdown` format — all three are decided-supported
  in both).
- `Image.configure(...)`: leave `resize` unset/false here — task 04 turns it on
  conditionally for HTML-mode fields as part of the UI work. This task's `Image` config
  should work correctly with `resize` off in both formats (i.e. establish the
  non-resize baseline round-trip first).
- **Introduce vitest**: `npm install -D vitest`, add `"test": "vitest run"` to
  `package.json` scripts. Do not update CLAUDE.md's Commands section yet — that's
  task 08, once the full test surface exists.
- Test file: `resources/js/wysiwyg.test.js` (co-located with the source file it covers,
  per the grill decision — not a `__tests__/` directory).

## Depends on

Task 01 (allow-list widening) — not because the vitest tests need it directly (they
instantiate a headless `Editor` and never touch PHP/HTTP), but because this task
establishes the "final" extension configuration that later HTML-mode saves need the
sanitizer to already accept; sequencing backend-before-frontend avoids a window where
the editor can produce output the sanitizer would strip.

## Key decisions already made

- No `@tiptap/extension-task-item`/`-task-list` packages — use `@tiptap/extension-list`'s
  exports. Flag this explicitly in the commit/PR description; it's an easy mistake.
- `Table`/`Image`/`TaskItem`/`TaskList` all apply to both formats unconditionally at the
  extension-config level — any format-specific restriction (resize, merge/split) is a
  **UI-layer** decision (task 04), not an extension-config gate here.
- Confirm `@tiptap/extension-table`'s actual export shape (single package with named
  exports vs. subpath exports like `@tiptap/extension-table/table-row`) against the
  installed package — `../expanded/architecture.md` flags this as unresolved import
  ergonomics, not a design question.

## Docs to consult

- `../expanded/architecture.md` ("Package changes" and `wysiwyg.js` changes §1–3).
- `../expanded/testing.md` ("JS: introduce vitest" section) for the full vitest setup
  checklist and the specific round-trip/normalization/fallback cases to cover.
- `../expanded/spec.md`'s "The pivotal unknown — verified" section for why these two
  extensions need no hand-written serializers (they ship real `parseMarkdown`/
  `renderMarkdown`), and its "Two round-trip gaps" bullets for the two accepted losses
  this task's tests must reproduce exactly (merged cell, resized image — though resize
  itself isn't wired until task 04, so that specific case may need to wait or be tested
  by hand-constructing a doc with `width`/`height` attrs already set).

## Tests

All in `resources/js/wysiwyg.test.js`, per `../expanded/testing.md`:

- Table, image, and task-list content round-trips losslessly in both `html` and
  `markdown` format (Markdown in → `getMarkdown()` out stable; HTML in → `getHTML()` out
  stable).
- The merged-table-cell loss reproduces exactly as documented: survives in `html`
  format, loses the merge in `markdown` format (hand-write GFM/HTML with a colspan —
  the editor's own UI can't produce one yet since task 04 hasn't shipped merge
  controls).
- Normalization cases stay cosmetic, not lossy: `_em_` → `*em*`, reference-link →
  inline link, bullet-marker changes — assert rendered meaning is unchanged.
- Nested blockquotes preserve depth; hard line breaks preserve the break (both already
  safe today per `spec.md` — regression-guard them here alongside the new constructs).
