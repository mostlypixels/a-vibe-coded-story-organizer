# Canonical punctuation on import — plan

## Order

| # | Task | Purpose |
|---|---|---|
| 01 | `fixture-and-oracle` | Write the definition down as `tests/Fixtures/punctuation.json`; check it against SmartPunct. |
| 02 | `canonical-punctuation-core` | `CanonicalPunctuation::inPlainText()` — the convention, one text run. |
| 03 | `markdown-and-html-shredders` | `inMarkdown()` / `inHtml()` — route prose to the core, skip code. |
| 04 | `content-sanitizer-hook` | `ContentSanitizer` returns normalized text; importers use the return value. |
| 05 | `drop-exporter-smartpunct` | Remove `SmartPunctExtension` from `EpubExporter` and repair its tests. |
| 06 | `editor-quote-fix` | Fix `'90s` in the input rules; assert the editor against the fixture. |
| 07 | `paste-normalization` | `transformPastedText` plus a JS normalize function, asserted against the fixture. |
| 08 | `documentation` | Rewrite the docs and comments that describe the deleted invariant. |

Each task depends on the one before it, except 06 which depends only on 01.

## Binding decisions — do not re-litigate

- **SmartPunct cannot be moved.** `league/commonmark` has no Markdown writer, and scene contents
  must stay Markdown source. SmartPunct is the fixture **oracle**, not the implementation.
  See `expanded/overview.md` → *Correction to the source spec*.
- **Hook point is `ContentSanitizer`**, not `ProjectGraphImporter`. `ManuskriptImporter`
  (branch `manuskript-import`) calls the sanitizer directly and must inherit this for free.
- **Fixture lives at `tests/Fixtures/punctuation.json`.** Neutral ground. One file, three
  implementations asserting against it.
- **Three implementations are accepted**: PHP, JS input rules (typing), JS normalize (paste).
  The fixture is what stops them drifting. A case may not live in only one suite.
- **`'90s`** — SmartPunct wins. An apostrophe before a digit is an elision, not an open quote.
- **`rock 'n' roll`** — stays wrong, recorded in the fixture with a note. A documented wrong
  answer in all three places still satisfies "one definition".
- **No backfill.** Pre-V1; `migrate:fresh --seed`.
- **No escape mechanism** for a literal `--`.
- **`StaticSiteExporter` needs no work.** Verified: it reads `renderedContents` and
  `RichText::toPlainText()`, so canonical storage covers it.

## Invariants every task must preserve

- **Validate, then normalize.** The allow-list check runs first and still throws. Normalizing
  before it would judge an import on text the archive never held.
- **Code is never touched.** Fenced blocks, indented code, backtick spans, `<pre>`, `<code>`,
  and HTML attribute values.
- **Idempotent.** Normalizing twice equals normalizing once. Re-importing an exported project
  must change nothing.
- **Prose fields only.** Titles and names go through `readJson()` and stay untouched.
- **The fixture is the contract.** No suite may hold a punctuation case the fixture does not.
