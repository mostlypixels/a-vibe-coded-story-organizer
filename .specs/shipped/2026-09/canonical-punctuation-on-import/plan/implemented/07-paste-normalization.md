# 07 — Normalize pasted text

**Depends on:** 06.

## Scope

- A JS normalize function implementing the convention over a whole block (input rules cannot —
  they are anchored to the last keystroke).
- Wire it through Tiptap's `transformPastedText`. Skip code blocks and code marks, same rule as
  `inHtml()`.
- Extend `resources/js/punctuation.test.js`: every fixture case asserted a **third** way, by
  pasting rather than typing.

**Not in scope:** nothing else. This is the last implementation task.

## Key decisions

- **Three implementations is the accepted design** (PHP, JS typing, JS paste). The fixture is
  what stops them drifting. Do not attempt to derive this function from the input-rule regexes
  — they are `$`-anchored and will not hold for quotes over a block.
- Client-side only. No endpoint, no round-trip; paste must work offline.
- Idempotent, and it must not fight autosave — pasting normalizes once, at paste time.

## Consult

`expanded/overview.md` → *Fourth consequence*; task 02 for the quote-direction rule to mirror.
