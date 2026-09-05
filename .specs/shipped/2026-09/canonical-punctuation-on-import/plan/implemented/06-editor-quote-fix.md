# 06 — Fix `'90s` in the editor

**Depends on:** 01.

## Scope

- `resources/js/wysiwyg.js` — stop `openSingleQuote` firing when a digit follows. Everything
  else in `TYPOGRAPHY_RULES` stays; `EmDashFromThreeHyphens` is untouched.
- New `resources/js/punctuation.test.js` — import `tests/Fixtures/punctuation.json`, type each
  `input` into a Tiptap editor keystroke by keystroke (reuse the `wysiwyg.test.js` helpers),
  assert the document text equals `expected`.

**Not in scope:** paste (task 07). No new JS normalize function here.

## Key decisions

- The typing path stays input-rule based. Do not replace it with a normalize-on-pause scheme;
  text changing under the cursor is unacceptable in a writing app.
- A fixture case may not be skipped in this suite. If the input rules genuinely cannot express
  a case, that is a finding for `resolution-log.md`, not a skipped assertion.

## Consult

`expanded/architecture.md` → *JS side*; `resources/js/wysiwyg.js` lines 65–100.
