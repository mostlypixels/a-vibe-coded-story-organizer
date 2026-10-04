---
status: draft
---

# Verse Block

Novels hold songs, poems and rhymes inside the prose. Their shape is part of the text: line breaks, stanza breaks, and indented lines such as a refrain. The editor has no block for this. Writers must use hard breaks in a normal paragraph or a blockquote. Both lose the indents. A blockquote also says "quotation", and the app renders it as one: italics, and curly quote marks around the whole block.

Found while importing *Les Misérables* as a test project. Verse appears in 32 of 365 chapters. The songs indent refrain lines one or two steps. The import now puts verse in blockquotes as a stopgap (`.scratchpad/les_miserables/tools/split.php`).

## Goals

- A verse block in scene prose. It keeps the line breaks, the stanza breaks and each line's indent level.
- The writer can make, edit and remove it in the editor without typing markup: slash menu, toolbar, keyboard.
- It survives the round trips: the editor and stored Markdown, and export and import of the archive.
- It renders as verse wherever scene prose renders: scene view, story overview, shared scene, `books/` reading layer, EPUB.
- Word counts, search and codex references treat verse text like prose.

## Non-goals

- Rhyme, meter or syllable tools.
- Free positioning or columns. Indent comes in a few fixed steps, not spaces or pixels.
- Centred, right-aligned or signed text blocks (letters, inscriptions). See the related findings below.
- Converting blockquotes that already hold verse.

## Rough approach

- Follow the `Callout` node in `resources/js/wysiwyg.js`: a custom Tiptap node with its own Markdown shape that plain Markdown readers still render as something sensible.
- Render through `App\Support\AuthorMarkdown`, and extend the sanitizer allow-list for the verse markup.
- `EpubExporter` and the `books/` layer of `StaticSiteExporter` follow the rendered HTML. Check the EPUB CSS.
- The archive stores raw Markdown, so the format needs no change if the Markdown form round-trips.

## Open questions

- **Markdown form.** Candidates: a callout-style marker (`> [!VERSE]`), a fenced block, or a directive (`:::verse`). What does a plain Markdown reader show for each?
- **Indent form.** How does one line's indent level live in Markdown without invisible whitespace?
- **Stanza break.** Is an empty line inside the block enough?
- **Scope.** Does the same block fit letters and inscriptions, or do those need a separate feature?

## Related findings from the import

Other formatting in *Les Misérables* the app cannot hold. Each could become a separate spec:

- **Footnotes** (16 chapters, 66 notes). They now live in scene notes. Real footnotes would give EPUB note links.
- **Aligned paragraphs** (about 17 centred, 12 right-aligned): notices, inscriptions, the signature under a letter.
- **Small caps** (22 chapters): names and headings inside quoted documents.
- **Foreign-language passages** (Latin in 10 chapters, English in 1). A language mark would help EPUB hyphenation and screen readers.
- **Tables with dotted leaders** (a few chapters: an inn bill, a budget). Tables exist in the editor; the leaders are presentation only.
