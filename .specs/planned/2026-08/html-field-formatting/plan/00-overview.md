# HTML field formatting — plan overview

The manual for this plan. Never implemented, never moved to `implemented/`.

HTML fields gain block alignment and named text colour. Markdown scene text gains nothing:
it becomes EPUB body and is read aloud by TTS, so it stays structural.

## Execution order

| # | Task | Purpose |
|---|---|---|
| 01 | `registry-and-sanitizer-profiles` | The closed class sets, and the lock that keeps them out of Markdown |
| 02 | `application-styles` | What the classes look like on screen, from theme tokens |
| 03 | `epub-styles` | What they look like in a book, where there are no themes |
| 04 | `editor-extensions` | Tiptap marks and node attributes, registered for HTML only |
| 05 | `toolbar-and-slash-menu` | The two dropdowns, gated from data |
| 06 | `diff-support` | The compare screen learns to see colour and alignment |
| 07 | `documentation-sweep` | `rich-text.md`, `revisions.md`, `themes.md`, changelog |

## Binding decisions

Settled in the grill. Later tasks must not re-litigate them. The reasoning is in
`expanded/open-questions.md`.

- **Five colours: `red`, `green`, `amber`, `blue`, `grey`.** No sixth without a new grill.
- **Every colour's value comes from an existing theme token**, never a new literal:

  | Name | Token |
  | --- | --- |
  | `red` | `--color-danger-surface-content` |
  | `green` | `--color-success-surface-content` |
  | `amber` | `--color-warning-surface-content` |
  | `blue` | `--color-info-surface-content` |
  | `grey` | `--color-content-subtle` |

  `ThemeTokens::PAIRS` already contrast-checks all five against `surface`, `surface-raised`
  and `surface-sunken` in every preset, and every preset keeps the same hue. That is what
  buys this feature free dark mode and free per-theme correctness — do not introduce a
  parallel palette.
- **The stored class carries the colour name, not the token name** — `rt-color-red`, never
  `rt-color-danger`. The author's intent is "red"; the token is where we get a safe red.
- **Alignment is `left`, `center`, `right`, `justify`.** `left` is the default and writes no
  class, so existing content needs no migration.
- **Classes are prefixed `rt-`**, clear of Tailwind and of the editor chrome (`.wysiwyg-slash`).
- **Two sanitizer profiles**, `Rich` and `Structural`. Not per-field: no caller needs it.
- **Every HTML field gets the controls** — descriptions, `Scene.notes`, codex. Only codex
  descriptions reach the EPUB appendix, which is what makes appendices the place decoration
  shows up in a book.
- **No new npm dependency.** Stock `@tiptap/extension-text-align` and the `TextStyle` colour
  mark both emit inline `style`, which the sanitizer strips. Both are written locally.
- **No inline `style`, ever.** Closed class sets only.
- **Diff support is in scope** (task 06), against the original recommendation to defer it.

## Invariants every task must preserve

- **The Markdown lock is one method.** `AuthorMarkdown::render()` passes `Structural`.
  `ContentSanitizer::assertMarkdownAllowed()` and `EpubExporter::renderSceneContents()` do
  too. Any new path that renders author Markdown must pass `Structural` — if a task adds
  one, it adds the test that proves it.
- **Divergence lives in data, not in a second component.** One `WysiwygToolbar`, one
  `buildExtensions()`, one `wysiwyg.blade.php`. Markdown diverges by empty item arrays and
  `!isMarkdown`, never by a Blade conditional or a forked file.
- **The toolbar and the slash menu are gated by the same boolean**, asserted by a test
  rather than promised by a comment.
- **The value sets have one home.** `RichTextFields` constants feed the sanitizer, the
  toolbar, the CSS tests and the JS list. No literal `'rt-color-red'` anywhere a constant
  could be read instead.
- **Colour and alignment never carry meaning alone.** Nothing reads them back; no behaviour
  keys off them.
- **A reader's own EPUB stylesheet must be able to win.** Single-class selectors, no
  `!important`.
- **The suite is green at the end of every task.** No task may hand the next one a red suite.

## Where to look

`expanded/overview.md` for goals and acceptance criteria, `architecture.md` for the registry,
the profiles and the editor extensions, `ui.md` for the toolbar and the class contract,
`testing.md` for the test strategy, `open-questions.md` for every decision and why.
