# 02 — `CanonicalPunctuation::inPlainText()`

**Depends on:** 01.

## Scope

- New `app/Support/CanonicalPunctuation.php` with one public method:

  ```php
  public static function inPlainText(string $text): string;
  ```

- Holds the whole convention: dashes, ellipsis, quote direction. Input is one prose run with no
  markup and no code.
- `tests/Unit/Support/CanonicalPunctuationTest.php` — data provider reads
  `tests/Fixtures/punctuation.json`, asserts every case.

**Not in scope:** Markdown or HTML awareness (task 03), any wiring (task 04).

## Key decisions

- A `Support` class, not a `Service`: pure text, no dependencies, no I/O. Model the shape and
  the docblock on `App\Support\AccentFolder` — same "single source of truth, hand-rolled, here
  is why" pattern.
- **Quote direction rule:** a `'` or `"` opens only when the character before it is
  start-of-run or whitespace **and** the character after it is not a digit. Digit after means
  elision, so it closes. This is a simplification of CommonMark's left/right-flanking delimiter
  run; the fixture is what proves it close enough.
- Must be idempotent. Add a test that runs every fixture `expected` through the method and
  gets it back unchanged.

## Consult

`expanded/architecture.md` → *New: `app/Support/CanonicalPunctuation.php`*.
