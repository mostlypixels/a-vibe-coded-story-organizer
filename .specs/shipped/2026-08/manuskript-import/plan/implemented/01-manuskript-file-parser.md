# 01 — Manuskript file parser

**Depends on:** nothing.

## Scope

* `app/Services/Import/ManuskriptFile.php`: read one file, return its header map and its body.
* Nothing domain-aware — no knowledge of chapters, scenes, characters, or which keys matter. Those
  live in tasks 03–05.

## Key decisions

* Format and parsing rules: `../expanded/architecture.md` → *The file format*. Header line, padded
  continuation lines, first blank line ends the header, body verbatim.
* Keys are trimmed and matched case-insensitively (`title:` vs `Name:`).
* CRLF → LF normalization happens **here**, on read, so no caller can forget it.
* A line that looks like a header but sits after the blank line stays in the body — prose contains
  `Note: something`.

## Tests

`tests/Unit/ManuskriptFileTest.php`: continuation lines joined with `\n`; header-only file (no
body); body-only edge case (file starting with a blank line); a header-shaped line inside the body;
CRLF input; UTF-8 accents preserved; missing file throws.
