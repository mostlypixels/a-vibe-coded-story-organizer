---
status: draft
---

# Canonical Punctuation On Import

## Problem

The same authorial intent has two stored forms. `–` if a writer typed it, `--` if the text
arrived by import, because Tiptap's input rules (#122) fire on keystrokes and never rewrite
loaded content.

Three consequences, none visible until export:

- Editing an imported paragraph converts only the retyped part, so one paragraph holds both
  forms.
- `EpubExporter` runs SmartPunct over scene Markdown, so an imported story converts in the
  book — but rich HTML fields have no such pass, so the appendix beside it does not. One
  EPUB, two typographic standards.
- The convention now lives in two places that must agree by hand: the editor's input rules
  and the exporter's SmartPunct pass. #122 had to reconcile them once already.

## Goals

- Text is canonical the moment it is stored, whatever route it arrived by.
- One implementation of the convention, not two that agree.
- Remove SmartPunct from `EpubExporter` — with canonical storage it has nothing left to do.
- The appendix and the story read the same.

## There is no written definition today

"Canonical" currently means "whatever the editor's input rules and CommonMark's SmartPunct
both happen to do". They are two independent implementations — a regex input rule per
transform in JS, a delimiter processor in PHP — and nothing asserts they agree.

Probed across 17 inputs: 16 agree, 1 does not.

| Input | Editor | SmartPunct |
| --- | --- | --- |
| `the '90s` | `the ‘90s` | `the ’90s` |

SmartPunct is right: `'90s` is an elision, so the mark leans like an apostrophe. The
editor's `openSingleQuote` regex sees a space and opens a quote. Both are also wrong
together on `rock 'n' roll`, opening the first mark instead of eliding it.

So this feature cannot start by "moving" a convention. **Writing the definition down is the
first piece of work**, as a fixture table both suites assert against, and the `'90s` case
is a bug in what #122 shipped.

## Non-goals

- No change to the dash and ellipsis rules. CommonMark's `--` → en dash, `---` → em dash,
  `...` → ellipsis. Settled in #122 and verified identical across both implementations.
- Not a general typography engine. No hanging punctuation, no spacing rules, no
  locale-specific quote styles.
- No user-facing toggle, per field or per project.
- No new punctuation transforms.
- No normalization of anything but punctuation.
- Not a general "clean up on write" hook. Import is the seam; typed text is already
  canonical when it arrives.

## Rough approach

- Normalize in `app/Services/Import/`, beside `ContentSanitizer` — the existing choke point
  every imported field already passes through.
- Scene Markdown needs no new logic: CommonMark's `SmartPunctExtension` already implements
  exactly this convention. Move that pass from export to import.
- Rich HTML has no equivalent. It needs a text-node walk applying the same substitutions.
- Skip code. `pre`, `code`, and fenced/inline Markdown code are not prose.
- Delete the SmartPunct wiring from `EpubExporter::converter()` once nothing depends on it,
  and the test that asserts the export converts.

## The principle this bends

`ContentSanitizer`'s docblock states that imports **fail** rather than change bulk content
silently. That rule was written for disallowed markup — a security and allow-list concern.
Punctuation is a different category, but the exception has to be written down where the
rule is, not slipped past it.

## Drift risk

The convention exists in JS (editor input rules) and PHP (SmartPunct), and will still exist
in both after this: the editor must convert as the writer types, and import must convert
what the writer never types.

A shared fixture file is the contract — one list, asserted by the PHP suite and the JS
suite, failing when either drifts. It is the deliverable that makes "canonical" mean
something, and it is what would have caught the `'90s` divergence.

## Open questions

- Existing rows hold un-normalized imports. Backfill, or leave? Pre-V1 the only data is the
  seed, so a reseed is probably enough.
- Does the static site export need the same treatment, or does `RichText::toPlainText()`
  make it moot?
- Should a writer be able to keep a literal `--` in prose, and if so how — an escape, or
  simply undo, which already reverts an input rule in the editor but has no equivalent on
  the import path?
- When the two disagree, which wins? SmartPunct is the better implementation on the
  evidence, but it is the one this feature was going to delete. Teaching the editor
  SmartPunct's quote logic is harder than it sounds — regex input rules see one keystroke,
  not a document.
- Is `rock 'n' roll` worth solving at all, or is a known-wrong shared answer acceptable?
- `ManuskriptImportCommand` (branch `manuskript-import`, unmerged) is a second import route.
  Does it inherit this automatically by going through `ContentSanitizer`?
