---
title: Expand Tip Tap — Testing
---

# Testing

This spec's stated deliverable is as much the **tests** as the code — `spec.md`: *"A
round-trip test is the deliverable that stops this regressing on the next TipTap bump."*
Two test tiers: PHP (existing tooling, `composer test`) and a **new** JS tier (this spec
is the first consumer of vitest, per `autosave-with-revisions` §9.12's proposal).

## JS: introduce vitest

No JS test tooling exists today (confirmed — no Dusk, no vitest, no `test` script in
`package.json`). Add it here rather than waiting for `autosave-with-revisions`, since
this spec has the first genuine round-trip logic worth pinning:

* `npm install -D vitest` (one devDependency, per `autosave-with-revisions`' own framing
  of the tradeoff — "it forces the better structure anyway").
* Add `"test": "vitest run"` to `package.json` scripts.
* **CLAUDE.md's Commands section must be updated** to list `npm run test` as a canonical
  command — flagged in `autosave-with-revisions` §9.12 as a note for whoever adds it
  first; that's this spec.
* Test file location: `resources/js/__tests__/wysiwyg.test.js` (or co-located
  `resources/js/wysiwyg.test.js` — match whatever convention `vitest.config.js`'s
  default `include` pattern expects; no existing JS test file to match against).
* Tests instantiate a headless Tiptap `Editor` (no DOM/browser needed — Tiptap's core
  works in a jsdom-less unit-test context for content-only operations like
  `getMarkdown()`/`getHTML()`) with the same `extensions` array `wysiwyg.js` builds, for
  each of `html` and `markdown` format, and assert:
  * Markdown in → `editor.getMarkdown()` out is stable for a construct that should
    round-trip losslessly (tables, images, task lists, callouts, `<u>` underline).
  * The two **documented, accepted** losses reproduce exactly as described, not more:
    * A merged table cell (colspan/rowspan in the source Markdown — hand-write the GFM
      since the editor's own UI won't produce one in markdown mode once prevention
      ships) survives in `html` format but loses the merge in `markdown` format.
    * A resized image (`width`/`height` present in source HTML) survives in `html`
      format but loses the dimensions in `markdown` format.
  * The normalisation cases `spec.md` documents are exactly cosmetic, not lossy:
    `_em_` → `*em*`, reference-style link → inline link, bullet-marker changes. Assert
    the *rendered meaning* is unchanged (e.g. still italic, still linking to the same
    URL) even though the literal Markdown text differs.
  * Nested blockquotes preserve depth; hard line breaks preserve the break; a callout's
    `[!TYPE]` marker round-trips exactly.
  * The three fallback-list structural checks (see below) correctly flag their target
    case and correctly do NOT flag the normalisation cases — the "no false positives"
    requirement `spec.md`'s fallback-policy section states explicitly.

## PHP tests

Follow the existing style (`tests/Feature/*Test.php`, `tests/Unit/*Test.php`): plain
PHPUnit, `RefreshDatabase` where a model write is involved, `actingAs($user)`, `route()`.

* **`tests/Unit/Rules/ValidMarkdownTest.php`** (new, or extend if one exists under a
  different path — none found today): a scene body containing `~~struck~~` and a GFM
  task-list checkbox (`- [ ] todo`) both validate successfully once the converter switch
  lands; add a regression test asserting they validated *before* the switch too (GFM
  syntax was never rejected, just misinterpreted) so the test documents the actual fix —
  meaning changed, not acceptance.
* **`tests/Unit/Services/EpubExporterTest.php`** (existing file): add a case asserting a
  scene with `~~struck~~` content renders `<s>` (or `<del>`, confirm
  `StrikethroughExtension`'s actual output tag) in the exported EPUB's XHTML, where
  today it would render literal tildes. Same for a task-list checkbox rendering as an
  actual list-with-checkbox construct, not literal `[ ]` text, if `TaskListExtension` is
  added to this converter per `architecture.md`.
* **`tests/Feature/RichTextRenderingTest.php`** (existing file) and
  **`tests/Unit/RichTextTest.php`**: extend coverage for the widened `ALLOWED_TAGS` —
  a `<table>`/`<img>`/task-list fragment saved through a rich-HTML field (e.g.
  `Project.description`) survives `HtmlSanitizer::clean()` unchanged, while an
  `<iframe>` or `<script>` still gets stripped (negative case, guards against an
  over-broad allow-list edit).
* **Import path regression** (`app/Services/Import/ContentSanitizer.php` consumers —
  check `tests/` for its existing test file): confirm a `.zip` import containing a
  Markdown table/image/task-list now **passes** `assertMarkdownAllowed()` where it may
  have previously been rejected as "disallowed HTML content" once its rendered HTML
  included a `<table>`/`<img>` that wasn't yet in the allow-list. This is a real
  behavior change worth a named test, not just an implicit side effect of widening
  `RichTextFields::ALLOWED_TAGS`.
* **`documentation/rich-text.md`/`WysiwygFormTest.php`**: no textarea-rendering behavior
  changes (the progressive-enhancement contract is untouched), but if the toolbar gains
  new always-visible buttons, `WysiwygFormTest`'s HTTP-level assertions
  (`assertSee('<textarea'...)`) stay valid without modification — worth confirming by
  running the existing suite, not adding new assertions there.

## What NOT to test here

* Anything about autosave, coalescing, or the warning's UI/copy/dismissibility — that's
  `autosave-with-revisions`' test surface once it's unblocked by this spec.
* Image upload/storage — out of scope per `overview.md`'s Non-goals; no upload endpoint
  exists to test.
* Footnotes — `.specs/draft/footnote-plugin`'s concern.

## Parallel-test constraints

Per CLAUDE.md: `composer test` runs in parallel via paratest, one SQLite DB per process
— no shared state assumptions in any new `RefreshDatabase` test. The new vitest tier has
no such constraint (pure content-transform tests, no shared fixtures), but keep each test
file independent regardless, matching the PHP suite's discipline.
