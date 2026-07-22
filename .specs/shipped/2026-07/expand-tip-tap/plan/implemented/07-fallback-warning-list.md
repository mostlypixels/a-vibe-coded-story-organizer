# Task 07 — Fallback-warning structural-check list

## Scope

The deliverable `autosave-with-revisions` §11.5.2 is waiting on: a small, standalone,
independently-testable module implementing exactly three structural checks against a
parsed editor document — not a text diff, so cosmetic normalization (task 03's
`_em_` → `*em*` cases etc.) never false-positives:

1. **A table containing a merged cell** — colspan/rowspan present in the parsed
   document's table nodes.
2. **An image with `width`/`height` attributes set** — only meaningful/reachable in
   Markdown-mode fields per task 04's HTML-only resize decision (an HTML-mode field
   never needs this warning, since resize is lossless there — but the check itself
   should still work format-agnostically; the caller, not this module, decides when to
   invoke it).
3. **An HTML block whose outer tag matches no registered node's `parseHTML` rule** —
   the unmatched-wrapper-tag case (`spec.md`'s `<div class="letter">` example).

Location: a new small module, e.g. `resources/js/wysiwyg/fallbackChecks.js` (exact path
at implementer's discretion, but keep it separate from `wysiwyg.js` itself — this task
is explicitly "standalone," confirmed in the grill, precisely so
`autosave-with-revisions` can depend on one clear file without pulling in toolbar/Alpine
code). Export one function per check plus a combined "any warning?" aggregate, since
`autosave-with-revisions` will likely want both the aggregate and the detail for its
copy/UI.

This task does **not** build any UI — no warning banner, no dismissal state, no copy.
That's explicitly `autosave-with-revisions`' concern per `spec.md`. This task's output
is a pure function returning which of the three cases (if any) apply to a given parsed
document.

## Depends on

Task 04 (tables and images must be fully wired, including merge/split and resize, for
there to be a real merged-cell/resized-image case to detect) and task 06 (callout
must exist and be confirmed *excluded* from this list — the check module should have a
test proving a callout never triggers the unmatched-HTML-tag check, since a naive
implementation might mistake its custom node for "unmatched").

## Key decisions already made

- **Standalone task/module** — confirmed in the grill specifically so
  `autosave-with-revisions` gets one dependency, not three checks scattered across the
  tables/images/HTML-fallback tasks.
- **Structural checks against the parsed document, not a diff.** This is what makes the
  "no false positives on cosmetic normalization" guarantee possible — don't implement
  any of the three as a text comparison.
- **Underline and callouts need no entry in this list** — both are designed to
  round-trip without loss (per `spec.md`'s explicit statement); a test should assert
  this explicitly, not just omit them by absence.
- **Footnotes are entirely out of this list** — `footnote-plugin` owns that decision;
  today's degrade-to-plain-text behavior isn't a loss this list needs to catch.

## Docs to consult

- `../expanded/spec.md`'s "Fallback policy" section (under "Rough approach") for the
  full three-check enumeration and the "Chosen" rationale.
- `../expanded/testing.md`'s bullet on "the three fallback-list structural checks... no
  false positives" for the specific test shape expected.

## Tests

- `resources/js/wysiwyg.test.js` or a co-located `fallbackChecks.test.js` (match
  whichever convention task 03 established):
  - Each of the three checks correctly flags its target case (a real merged-cell
    document, a real width/height-bearing image document, a real unmatched-wrapper-tag
    document) and correctly does **not** flag any of: a plain table/image/task-list, an
    underline mark, a callout block, or any of the normalization cases from task 03's
    tests (`_em_` → `*em*`, reference-link → inline, bullet-marker changes).
  - The combined aggregate function returns all applicable cases for a document
    containing more than one flagged construct at once (e.g. both a merged cell and an
    unmatched wrapper tag in the same document).
