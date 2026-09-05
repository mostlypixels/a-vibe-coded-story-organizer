# Testing

## The contract test, both suites

The same fixture file, asserted twice. This is the feature's point.

- `tests/Unit/Support/CanonicalPunctuationTest.php` — data provider reads
  `resources/js/punctuation-fixtures.json`, asserts `inPlainText()` matches `expected`.
- `resources/js/punctuation.test.js` — imports the JSON, types each `input` into a Tiptap
  editor keystroke by keystroke (the existing `wysiwyg.test.js` helpers do this), asserts the
  document text matches `expected`.

Neither suite may hold its own copy of a case. A case only in one file is the drift the
fixture exists to prevent.

## Oracle test

One PHP test asserting the fixture table against `SmartPunctExtension` itself — render each
`input` as Markdown with a SmartPunct converter, strip the `<p>`, compare to `expected`.
Deviations are allowed but must be listed explicitly in the test with a reason (`rock 'n' roll`
is the expected entry). This is what keeps "canonical" honest after SmartPunct is deleted from
the exporter.

## Import feature tests

Extend the existing `ProjectGraphImporter` feature tests rather than adding a new file.

- Scene `contents_file` with `--`, `...`, `"quoted"` → stored Markdown holds `–`, `…`, `“ ”`.
- Codex entry `description_file` (rich HTML) → same, inside `<p>`, `<strong>`, `<li>`.
- A fenced block and an inline code span in scene Markdown → byte-identical after import.
- `<pre><code>` in a rich HTML description → byte-identical.
- An import that fails the allow-list still fails, and stores nothing (validate-then-normalize
  order).
- Project/act/scene **titles** containing `--` are stored unchanged.

## Regression guards

- `tests/Unit/Services/EpubExporterTest.php:388` asserts the export converts punctuation.
  Rewrite it: the exporter no longer converts, so it must assert the EPUB carries through
  whatever the scene already holds. Delete the assertion, do not delete the test.
- Add: a scene whose stored contents are already canonical exports with those characters intact
  and the package still passes `validatePackage()` well-formedness.
- `resources/js/wysiwyg.test.js:618,655` comments justify rules by exporter agreement. Update.

## Edge cases worth a case each

- `--` at the very start of a line (a dialogue dash in some traditions).
- An apostrophe inside a word (`don't`) versus one closing a quote (`'word'`).
- `'90s` — the bug this fixes.
- A `"` immediately after an opening `(`.
- Text already canonical: normalizing twice must be a no-op (idempotence).
- An HTML attribute value containing `--` — the text-node walk must not touch it.
