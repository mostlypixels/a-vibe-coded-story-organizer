# 01 — Fixture file and SmartPunct oracle

**Depends on:** nothing.

## Scope

- Create `tests/Fixtures/punctuation.json`: a flat list of `{input, expected, note?}`.
- Create `tests/Unit/Support/PunctuationFixtureTest.php` — the oracle. Render each `input` as
  Markdown through a converter carrying `SmartPunctExtension`, strip the wrapping `<p>`, compare
  to `expected`.
- Deviations from SmartPunct are allowed but must be an explicit, reasoned list in the test.
  `rock 'n' roll` is the one known entry today.

**Not in scope:** any implementation. No `CanonicalPunctuation` class (task 02), no JS
(tasks 06–07).

## Key decisions

- `tests/Fixtures/` is the home. The folder is arriving anyway with `manuskript-import`.
- SmartPunct wins every disagreement. `the '90s` → `the ’90s`, not `the ‘90s`.
- The fixture is data, readable by PHP and by Vitest with no build step. Plain JSON, no comments.

## Cases the fixture must carry

Dashes `--`/`---`, ellipsis `...`, double quotes, single quotes, `'90s`, `don't`,
`rock 'n' roll` (noted as wrong), `--` at line start, `"` after `(`, and already-canonical text
(idempotence). See `expanded/testing.md` → *Edge cases worth a case each*.

## Consult

`expanded/overview.md`, `expanded/testing.md` → *Oracle test*.
