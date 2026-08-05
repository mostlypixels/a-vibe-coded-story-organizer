---
status: draft
---

# Html Field Formatting

## Problem

HTML fields (descriptions, codex) want decorative richness. Markdown scene text must stay
minimal — it becomes EPUB body, read by TTS. One toolbar serves both today; only merge/split
cells diverge. Adding colors/alignment risks leaking into the restricted narrative body.

## Goals

- HTML fields gain alignment and text color.
- Markdown scene text stays structural-only.
- Divergence lives in data, not two toolbars.
- Decorative richness only reaches codex appendices.

## Non-goals

- No second toolbar component.
- No free-form inline `style`.
- No richness in Markdown scene fields.
- No color/align as sole meaning carrier.
- No font family or size (later, maybe).

## Rough approach

- Model HTML as superset of Markdown core.
- New HTML-only toolbar cluster(s), gated in `WysiwygToolbar`.
- Closed, class-based value sets — not raw `style`.
- Named alignment values; fixed palette mapped to classes.
- Format-aware sanitizer: HTML profile permits new classes.
- Markdown / `ValidMarkdown` path stays locked.
- Slash menu gated the same way (parity invariant).
- Editor marks/nodes register only when `!isMarkdown`.

## Accessibility guardrails (load-bearing)

- Every palette pairing meets 4.5:1 contrast.
- Classes overridable by user EPUB stylesheet.
- Color/align never the only signal.
- Justified alignment discouraged or omitted.

## Open questions

- Which color palette, how many entries?
- Alignment: left/center/right, or add justify?
- One shared sanitizer profile, or per-field?
- Do codex fields already ship to appendices unchanged?
