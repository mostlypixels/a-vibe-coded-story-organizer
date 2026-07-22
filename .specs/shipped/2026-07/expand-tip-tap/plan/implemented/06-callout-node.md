# Task 06 — Callout / alert block node

## Scope

A new custom Tiptap node recognizing GitHub's `> [!NOTE]` / `[!TIP]` / `[!IMPORTANT]` /
`[!WARNING]` / `[!CAUTION]` convention (already used in this repo's own
`documentation/*.md`, per CLAUDE.md) and rendering it as a styled box instead of a plain
blockquote — in **both** field formats, per the grill decision.

- New node definition (location: alongside the other extension config in
  `resources/js/wysiwyg.js`, or a small dedicated module if the schema/view logic is
  substantial enough to warrant one — implementer's call, matching the file's existing
  granularity).
- `parseMarkdown`: detect a blockquote whose first line is exactly `[!TYPE]` (one of the
  five known types); everything else about blockquote parsing is unchanged.
- `renderMarkdown`: re-emit `> [!TYPE]` + content — must exactly match the input
  convention so a plain-CommonMark reader's existing graceful-degradation (rendering it
  as an ordinary blockquote) keeps working. This is a correctness requirement, not
  cosmetic: breaking the exact `[!TYPE]` marker format would break the very
  degrade-gracefully property that makes this construct safe today.
- HTML mode: presentational rendering only — styled box (border colour + icon per type)
  over the existing `blockquote` element, using the `data-callout-type` attribute task
  01 already allow-listed.
- Slash menu entry (`'Callout'`, keywords `['note', 'tip', 'warning', 'alert', 'callout']`)
  and a toolbar button, both available in **both** formats (not gated by `isMarkdown` —
  callouts are decided-safe in both). Exact type-selection interaction (dropdown vs.
  cycling button vs. always-`[!NOTE]`-then-editable) is an implementation detail left
  open by `../expanded/ui.md`; pick the simplest that fits the existing toolbar's visual
  language.
- No PHP converter change needed: bare `CommonMarkConverter` (`ValidMarkdown`,
  `Str::markdown()`, `EpubExporter`'s converter) already renders `> [!TYPE]` as an
  ordinary blockquote — that's the existing degrade-gracefully behavior this task must
  not disturb.

## Depends on

Task 03 (vitest tier) and task 01 (the `data-callout-type` attribute must already be
allow-listed before an HTML-mode save round-trips it).

## Key decisions already made

- **Both formats**, confirmed in the grill — not Markdown-only despite callouts being
  framed around Markdown's blockquote convention in `spec.md`.
- The Markdown serialization must be **byte-exact** to the `> [!TYPE]` convention — this
  is what keeps the construct safe for readers/tools without callout support, a property
  worth a dedicated test (see below), not just an assumption.
- This is a **new node**, not a configuration change to the existing `Blockquote` from
  StarterKit — don't try to make `Blockquote` itself type-aware; add a sibling/extending
  node so plain blockquotes (no `[!TYPE]` marker) are entirely unaffected.

## Docs to consult

- `../expanded/architecture.md` §6 (Callout/alert node) for the parse/render contract.
- `../expanded/ui.md`'s Callout bullets for slash-menu/toolbar placement.
- This repo's own `documentation/*.md` files for the existing GFM alert-callout visual
  convention to match (per CLAUDE.md's own instruction to use these callouts).

## Tests

- `resources/js/wysiwyg.test.js`: a `> [!NOTE]\ncontent` document hydrates into the
  callout node (not a plain blockquote) and `getMarkdown()` re-emits the identical
  `> [!NOTE]` + content — byte-exact round-trip, both formats.
- A plain blockquote with **no** `[!TYPE]` marker is unaffected — still hydrates as an
  ordinary blockquote, regression-guarding against the new node accidentally widening
  its match.
- HTML-mode: the rendered box carries the `data-callout-type` attribute matching the
  type; confirm it survives `HtmlSanitizer::clean()` (depends on task 01's allow-list
  addition — add a PHP-side test alongside task 01's if not already covered there).
