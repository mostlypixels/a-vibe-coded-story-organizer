---
status: draft
---

# Footnote Plugin

Add footnote support to the TipTap editor and the `Scene.contents`/`RichTextFields`
Markdown-HTML pipeline.

## Why a separate spec

Split out of `.specs/draft/expand-tip-tap` — a sibling spec, not a dependency to block
on. That spec covers tables, images, task lists, strikethrough, underline, and callouts,
all of which reduce to "wire up an official TipTap extension + a bundled `league/commonmark`
extension." Footnotes don't fit that shape:

* **Verified, not assumed:** there is no official `@tiptap/extension-footnote` — `npm view`
  404s on it, and nothing from `ueberdosis` surfaces in a registry search. Supporting
  footnotes means building a wholly custom node (schema, view, `parseMarkdown`/
  `renderMarkdown` pair), a materially bigger lift than the extension-wiring work the rest
  of `expand-tip-tap` covers.
* `marked` (the parser `@tiptap/markdown` uses) also has no built-in footnote support.
  Confirmed by running `marked.lexer()` directly on `He read the letter[^1] twice.` /
  `[^1]: The letter he'd kept since the funeral.` — without a footnote plugin, neither line
  is tokenized specially; both come back as ordinary `paragraph` tokens with the bracket
  syntax as literal text.

## Current fallback behaviour (verified, informs the design)

Unlike an unsupported table — which `@tiptap/markdown`'s fallback parser silently
**deletes** entirely (a `table` token has no `.tokens` array, so the fallback's default case
returns `null`) — a footnote today **survives** a TipTap round-trip as visible, readable,
but unstyled literal text: no jump link, no superscript marker, no separator, just two
disconnected paragraphs. Whatever this spec decides, it isn't rescuing content from
destruction the way tables/images were; it's turning already-safe plain text into a styled,
linked construct. Worth weighing against the cost below.

## The open question to resolve before designing further

No concrete writing-workflow need for footnotes has come up yet, unlike underline
(novel dialogue/letter-marking convention, a real case raised during `expand-tip-tap`).
Before investing in a custom node, confirm there's an actual use case — citations,
author's-note asides, worldbuilding glossary cross-references, or similar — worth the
build cost. If there isn't one, the honest answer may be "leave footnotes as literal
text," matching today's behaviour, and close this spec without shipping anything.

## Goals (if pursued)

* A custom TipTap node pair for footnote reference + footnote definition, and how the two
  associate (by number, by id-like key, sequential re-numbering on edit, etc).
* The `marked`-side parsing needed — likely a `marked` plugin (e.g. `marked-footnote`) or a
  hand-written tokenizer extension registered alongside `@tiptap/markdown`; to be verified
  against the actual package the same way `expand-tip-tap` verified `Table`/`Image`, not
  assumed.
* `parseMarkdown`/`renderMarkdown` for the new node(s), producing standard
  `[^1]` / `[^1]: definition` syntax so plain-Markdown consumers (this app's own
  `EpubExporter`, any external tool) still degrade gracefully.
* PHP side is already covered: `vendor/league/commonmark/src/Extension/Footnote` ships in
  the `league/commonmark ^2.8` already installed here. Enabling it just needs the same
  consistency pass `expand-tip-tap` already decided for strikethrough — `ValidMarkdown`,
  `Scene::renderedContents`, and `EpubExporter`'s isolated converter all need to agree.
* Decide whether footnotes should also work in the 8 HTML `RichTextFields` fields, or stay
  a `Scene.contents`-only (Markdown-only) construct — and if the former, what
  `RichTextFields::ALLOWED_TAGS` widening it needs.

## Non-goals

* Everything already decided in `.specs/draft/expand-tip-tap` (tables, images, task lists,
  strikethrough, underline, callouts) — this spec is footnotes only.
* Redesigning the editor UI generally — that's `.specs/draft/editor-interface`.
