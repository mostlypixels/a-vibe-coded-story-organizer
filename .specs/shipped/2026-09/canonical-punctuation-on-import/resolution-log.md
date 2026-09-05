# Canonical punctuation on import — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

> [!IMPORTANT]
> An **exception log, not a work journal**. A task that went to plan gets no entry — the
> diff and the task file already record what was built. Bullets under the headings below,
> root cause first, no per-task sections.

## Feedback & decisions

- **SmartPunct cannot be moved from export to import.** `league/commonmark` has no Markdown
  writer, and scene contents must stay Markdown source. The source spec's main approach was
  wrong. SmartPunct is now the fixture oracle; the implementation is new.
- **Hook point moved to `ContentSanitizer`** (from `ProjectGraphImporter`). `ManuskriptImporter`
  on branch `manuskript-import` calls the sanitizer directly and would otherwise inherit
  nothing.
- **Paste is in scope** as its own task. The editor has no paste handling at all today, so a
  writer who drafts elsewhere and pastes bypasses the input rules entirely.
- **Three implementations accepted** (PHP, JS typing, JS paste), guarded by one fixture file.
  Collapsing the two JS paths was rejected: normalize-on-pause moves text under the cursor,
  and the input-rule regexes are `$`-anchored so they cannot be reused over a pasted block.
- **Fixture lives at `tests/Fixtures/punctuation.json`**, not `resources/js/`. The folder is
  arriving with `manuskript-import`, and the file is a contract rather than editor config.
- **`StaticSiteExporter` needs no work.** Verified: it renders stored text via
  `renderedContents` and `RichText::toPlainText()`.

## Deviations from the spec/plan

- **The "opens after whitespace" rule also accepts an opening bracket or a dash.** The plan
  states a quote opens only after start-of-run or whitespace, but the fixture case `("Hello")`
  needs `(` to count as an opener too. `CanonicalPunctuation::OPENERS` holds the extra
  characters: `( [ { < – — - /`. The digit-after test is unchanged.
- **`inHtml` parses with `LIBXML_HTML_NOIMPLIED`**, unlike `RichText::toXhtmlFragment()`, which
  lets the parser imply `<html>/<body>` and then serialises the body's children. `inHtml` must
  return the fragment byte-identical apart from punctuation, so it keeps no implied wrapper and
  serialises with `saveHTML()` (not `saveXML()`, which would rewrite void elements).
- **Indented code detection under-reaches.** A four-space line counts as code only after a blank
  line. An indented list-item continuation is therefore read as code and left un-normalized.
  Under-reach is the safe direction: it never damages code.

- **`'90s` is fixed by a second rule, not by narrowing `openSingleQuote`.** Task 06 asked to stop
  the Typography rule firing before a digit, but on the `'` keystroke the digit does not exist
  yet — Typography can only read the character before the quote. `ElisionApostropheBeforeDigit`
  in `resources/js/wysiwyg.js` therefore fires on the digit and rewrites `‘` back to `’`, the
  same shape as `EmDashFromThreeHyphens`.

- **Paste skips code by reading the selection, not by scanning the text.**
  `transformPastedText` receives plain text with no markup, so there is nothing in it to
  shred the way `inHtml()` does. `NormalizePastedPunctuation` in `resources/js/wysiwyg.js`
  instead returns the text untouched when the paste lands in a code block or under a code
  mark. It is registered as a ProseMirror plugin inside `buildExtensions()` rather than as
  an `editorProps` entry, so every caller — including the tests — gets it.

## Issues → resolutions

- **A stale docblock survived task 05.** `EpubExporter` line 171 still named the SmartPunct
  converter after the extension was removed. Caught in the ship-plan loop and folded into task
  08. Removing an extension means checking every comment that names it, not only the two the
  task file listed.
