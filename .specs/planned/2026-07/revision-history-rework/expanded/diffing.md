# Diffing — Revision History Rework

The largest and riskiest part of the feature. This document answers `spec.md`'s first open
question (*which library performs the HTML-aware visual diff*) with evidence, then
specifies the component that replaces it.

## Two diff strategies, chosen by `FieldKind`

| `FieldKind` | Fields | Strategy | Why |
|---|---|---|---|
| `Markdown` | `Scene.contents`, `Project.dedication/acknowledgements/preface/postface` | **Source diff** — unchanged, `jfcherng/php-diff` side-by-side, word detail | The writer types the Markdown. The markup *is* the content, and `contents` is the field she cares about most. |
| `Plain` | `Project.rights` | Source diff | Nothing to render. |
| `Rich` | the 8 TipTap HTML fields (`RichTextFields::FIELDS`) | **Visual diff** — new | She never authors or reads that HTML. `<strong>` moving is "bolded", not a tag change. |

`App\Services\RevisionDiffer::diff()` keeps its signature and becomes the router; every
existing call site is untouched.

## Library evaluation (the open question, answered)

Checked against Packagist on 2026-07-25 (`composer show -a <package>`):

| Candidate | Licence | Requires | Verdict |
|---|---|---|---|
| `caxy/php-htmldiff` v0.1.17 | **GPL-2.0** | `php >=7.3`, `ext-dom`, `ezyang/htmlpurifier ^4.7` | The only maintained option, and the algorithmic reference. **Rejected on licence**: this app ships as source (`install.sh`, Docker images, the repo itself); a GPL-2.0 dependency is a licensing decision far bigger than a diff view. |
| `rashid2538/php-htmldiff` v1.0 | GPL-2 | `php >=5.3` | Same licence problem, plus a single release and a PHP 5.3-era codebase on a `php ^8.5` app. |
| `icap/html-diff` v1.1.0 | (unstated) | `php >=5.3` | DaisyDiff port, unmaintained, no declared licence. Rejected. |
| `jfcherng/php-diff` v7 (already installed) | BSD-3-Clause | `php >=8.3` | Line/word oriented, **not HTML-aware**. Cannot be the whole answer — but see below. |

**Decision: build it in-house on a dependency we already have.** `jfcherng/php-diff`
depends on `jfcherng/php-sequence-matcher` (BSD-3-Clause, already in `vendor/`), which is
a plain Myers sequence matcher over **arbitrary string arrays**:

```php
(new SequenceMatcher($oldTokens, $newTokens))->getOpcodes();
// => [['eq'|'ins'|'del'|'rep', i1, i2, j1, j2], ...]
```

That is exactly the primitive an HTML-aware differ needs. What HTML-awareness adds is
*tokenisation* and *re-assembly* — both of which are specific to this app's sanitized,
narrow tag vocabulary (`RichTextFields::ALLOWED_TAGS`) and are better owned than vendored.
No new composer dependency, no licence question, ~3 focused classes.

> [!NOTE]
> Recorded here rather than in `resolution-log.md` because it is a design decision, not a
> deviation. If it ever needs revisiting, the fallback is "vendor caxy and relicense", and
> the fallback to *that* is "keep the plain-text projection for Rich fields" — i.e. today's
> behaviour, which is a known, shippable state.

## The in-house visual differ (`App\Services\Diff\`)

Four small classes, each independently testable:

```
HtmlBlock            (app/Support)  value object: tag, text, inline tokens, mark signature
HtmlTokenizer        parses purified HTML into HtmlBlock[]           (DOMDocument)
VisualHtmlDiffer     block diff + inline diff, produces DiffLine[]   (SequenceMatcher)
DiffHtmlRenderer     DiffLine[] -> safe HTML string                  (escapes everything)
```

### 1. Tokenise (`HtmlTokenizer`)

Parse with `DOMDocument::loadHTML` (the same round-trip `RichText::toXhtmlFragment()`
already uses, so the "parse sanitized HTML with DOM" pattern is not new here) and walk the
body's children into a flat list of **blocks**:

* block elements → one `HtmlBlock` each: `p`, `h1`–`h4`, `li` (one per item, carrying its
  list type and depth), `blockquote` (with its `data-callout-type`), `pre`, `tr` (one per
  row, cells joined), `hr`, `img`;
* each block records:
  * `text` — its concatenated text content, normalised (collapse runs of whitespace);
  * `tokens` — the inline token stream: each token is a word (or a punctuation run) plus
    the **mark stack** in force at that point (`strong`, `em`, `u`, `s`, `code`, `a[href]`);
  * `signature` — a stable string of the mark stack transitions, used to detect a
    formatting-only change (same `text`, different `signature`).

Tokens are compared as `"<marks>\u{1F}<word>"` strings so the sequence matcher sees a
formatting change as a real change without any special-casing.

### 2. Diff blocks, then words (`VisualHtmlDiffer`)

Two levels, mirroring `wikidiff2` (`notes/revision-ui-lexicon.md`):

1. Sequence-diff the block list keyed by `text` → `eq` / `ins` / `del` / `rep` opcodes.
2. For `eq` blocks whose `signature` differs → emit the block once, rendered, flagged
   **formatting changed**, with the specific marks added/removed listed for the badge.
3. For `rep` blocks → pair them up in order and run the inline sequence diff over their
   token streams; runs of `ins`/`del` tokens become `<ins>`/`<del>`.
4. For `ins` / `del` blocks → emit whole-block added / removed.

**Complexity cap.** Before step 3, if `count(oldTokens) * count(newTokens)` exceeds
`config('revisions.diff.max_word_complexity')` (default 2,000,000), skip the inline diff
and emit the pair as one removed block plus one added block. This is wikidiff2's
`maxWordLevelDiffComplexity` rule: a pathological rewrite degrades to a coarser diff
rather than making the request grind. The cap is configuration, never a literal.

### 3. Render (`DiffHtmlRenderer`)

Builds the output string itself, so the sanitisation order from
`notes/revision-compare-decisions.md` holds by construction:

> already-purified content in → diff → wrap changes → render. **Never purify after
> wrapping** (the author allow-list would eat `<ins>`/`<del>`), and never let unpurified
> content reach the renderer.

Rules:

* every text node goes through `e()`; nothing from the stored value is ever concatenated
  raw;
* the renderer emits only tags from its **own** allow-list constant
  `DiffHtmlRenderer::EMITTED_TAGS` (the block tags above, the inline marks above, plus
  `ins`, `del`, `span`) — a tag that somehow reached it from the content is dropped, not
  passed through;
* attributes are re-emitted from the parsed values, not copied as strings: `a[href]` only
  (re-checked against `RichTextFields::purifierAllowedSchemes()`), `img[src|alt]`,
  `li[data-checked]`, `blockquote[data-callout-type]`.

Result: a `RevisionDiffResult` whose `html` is safe to `{!! !!}` — the same contract the
current jfcherng output has, for the same reason (the producer escapes).

### `<s>` vs `<del>` — the tag-collision guard

`<del>` and `<ins>` must belong to the diff layer alone. Two guards, both cheap:

1. The editor's strikethrough already emits `<s>` (TipTap StarterKit `Strike`;
   `resources/js/wysiwyg.js` maps it to GFM `~~text~~`) and
   `RichTextFields::ALLOWED_TAGS` contains `s` and **not** `del`/`ins` — so the sanitizer
   strips any `<del>` an author could paste in. This is already true today; the feature
   adds a **unit test asserting it stays true**, since the whole diff layer depends on it.
2. `HtmlTokenizer` treats `s` as a mark like any other; `DiffHtmlRenderer` never emits it
   as a change marker.

## Summaries (`RevisionSummarizer`)

The history list needs "what changed" per row without diffing at render time. At write
time the summarizer runs the same differ against the row's predecessor and produces:

* `change_count` — the number of changed hunks (`ins`/`del`/`rep` opcodes at the block
  level for Rich, hunks for Markdown/Plain);
* `summary_html` — the **first** changed hunk, plus a little unchanged context either
  side, truncated by *rendered text length* to `config('revisions.summary.max_length')`
  (200 chars). Truncation counts text, not markup, and never splits a marker element.
  Marks (`<strong>` etc.) are stripped from the summary — only `<ins>`/`<del>` and text
  survive, because a list row is a scan target, not a reading surface.

When `change_count > 1` the view appends "and *N−1* more changes" linking to
`revisions.compare` with the pair prefilled.

Baseline rows (no predecessor) store `null` / `0` and render as "Initial value".

## Failure modes and how they are handled

| Risk | Handling |
|---|---|
| Malformed stored HTML (pre-sanitizer legacy row) | `DOMDocument` repairs it; libxml errors suppressed and discarded, as `RichText::toXhtmlFragment()` already does. |
| Enormous field (`Scene.contents` cap is 1,000,000 chars — Markdown, so source-diffed) | The complexity cap degrades to block level. Rich fields cap at 100,000. |
| A diff of two identical values | `RevisionComparison` skips unchanged fields before calling the differ. |
| An entity whose field had no revision at a save point (`null` side) | Rendered as "did not exist yet" / whole-value insert. |
| Diff HTML leaking into the author path | Impossible by construction: `x-diff` is a separate component with its own styles, and the renderer is the only producer of `<ins>`/`<del>`. |
