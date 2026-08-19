# 06 — Diff support

The compare screen learns to see colour and alignment. Without this, a colour-only edit
saves a revision that displays as "no change".

**Depends on:** 01. Independent of 04–05, but easier to eyeball after them.

## Scope

- **Colour is a mark.** `HtmlTokenizer::MARK_TAGS` records inline marks and already encodes
  a link as `a:<href>`. Record a coloured span the same way — `color:red`. `signatureOf()`
  then reports it as a formatting change with no further work, because the signature is
  built from mark transitions.
- **Alignment is a block attribute.** Follow the `data-callout-type` precedent exactly:
  collect it in the tokenizer's attribute map for `ALIGNABLE_TAGS`, and pass it through
  `DiffHtmlRenderer::blockAttributes()`.
- `HtmlBlock::matchKey()` must **not** gain either one. A recoloured or re-aligned paragraph
  has to still match its old self, or the differ reports a delete plus an insert instead of
  a formatting change. This is the trap in this task.
- Whatever label the UI already shows for `data-marks-added` / `data-marks-removed` needs to
  read sensibly for `color:red`.

**Not in scope:** changing what creates a revision. Colour-only edits already record one,
and should.

## Key decisions

- Reuse the two existing mechanisms rather than adding a third. The tokenizer's docblocks
  say what each field answers; colour is a "how it says it" question (signature) and
  alignment is a "what kind of block" question (attributes).
- `HtmlTokenizer` is explicitly not a security boundary and is for rich fields only. Nothing
  here touches the Markdown path.

## Consult

`expanded/architecture.md` → *Downstream consumers*. `app/Support/HtmlBlock.php` docblock —
it explains why `text`, `signature` and `tokens` are three separate fields, which is the
whole basis of this task.

## Tests

- `HtmlTokenizerTest`: a paragraph whose only change is a coloured word yields the same
  `text` and a different `signature`.
- `HtmlTokenizerTest`: the same paragraph with a changed alignment differs in attributes.
- Both cases keep an identical `matchKey()` — assert this directly; it is the regression
  that would otherwise surface as a mangled diff.
- `VisualHtmlDifferTest` / `DiffHtmlRendererTest`: the change is reported as
  formatting-only, not as a delete plus insert, and the rendered panel carries the
  alignment.
