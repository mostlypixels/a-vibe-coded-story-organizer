# Overview

## Problem

Punctuation has two stored forms for one authorial intent: real `–`/`—`/`…`/curly quotes when
typed, ASCII `--`/`...`/`'` when imported. Tiptap input rules fire on keystrokes only.

## Correction to the source spec

`spec.md` says "move the SmartPunct pass from export to import". **That is not possible as
written.** `league/commonmark` has no Markdown renderer — `SmartPunctExtension` is a
parse-and-render-to-HTML pass. Scene contents must stay Markdown *source* for the editor to
reload (`AuthorMarkdown` docblock). So import needs a Markdown-source → Markdown-source
transform that CommonMark does not provide.

Consequence: SmartPunct becomes the **oracle** for the fixture table, not the implementation.
The feature ships one new PHP transform, and SmartPunct's rendered output is what the fixtures
are checked against.

## Fourth consequence, missing from the source spec

**Paste is a third route.** Pasting into Tiptap does not fire input rules either. A writer who
drafts in another app and pastes gets ASCII, with no import involved. Import-only normalization
leaves this hole. See `open-questions.md`.

## Goals

- One written definition of "canonical punctuation", as a fixture table asserted by both suites.
- Text is canonical when stored, whatever route it arrived by.
- Fix the `'90s` divergence #122 shipped.
- `EpubExporter` drops its SmartPunct pass.

## Non-goals

Unchanged from `spec.md`. Note `rock 'n' roll` stays wrong on both sides unless
`open-questions.md` says otherwise — a known-wrong shared answer still satisfies "one
definition".

## Acceptance criteria

- One fixture file. PHP and Vitest both read it. Either implementation drifting fails a test.
- Importing an archive with `--`, `...`, `"quoted"`, `'90s` stores `–`, `…`, `“quoted”`, `’90s`
  in both `Scene.contents` and every `RichTextFields` HTML field.
- Code is untouched: fenced blocks, inline code spans, `<pre>`, `<code>`.
- `EpubExporter::converter()` no longer registers `SmartPunctExtension`; EPUB output for a
  freshly imported project is byte-identical to before.
- A non-owner import path is unaffected — this adds no new endpoint.
