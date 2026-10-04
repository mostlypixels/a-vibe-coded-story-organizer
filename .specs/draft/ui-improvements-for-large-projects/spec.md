---
status: draft
---

# Footnotes

Writers add footnotes to prose: a translation, a source, an aside from the narrator. The editor has no footnotes. A writer puts the note in brackets inside the text, or keeps it in the scene notes, where no reader of the book sees it.

Found while importing *Les Misérables* as a test project. There are 66 footnotes in 16 chapters: translations of Latin and patois, and dated remarks. The import moves them to scene notes and leaves `[1]` markers in the text as a stopgap.

## Goals

- The writer can add a footnote at the caret and write its text without leaving the scene.
- The marker and its note stay linked when the writer moves, cuts or pastes text.
- The numbers renumber automatically.
- It renders wherever scene prose renders: scene view, story overview, shared scene, `books/` reading layer, EPUB.
- The EPUB uses real note links: a reader taps the marker to see the note and taps back.
- It survives the archive round trip and the revision history.
- Word counts leave out the note text, or count it apart.

## Non-goals

- Endnotes collected at the end of the book, a bibliography, or citation styles.
- Notes on codex entries, events or other rich fields. Scene prose only.
- Converting the import's `[1]` markers or scene-note footnotes.

## Rough approach

- Markdown already has a common form for footnotes (`[^1]` with a `[^1]: …` definition). `league/commonmark` ships a footnote extension. Start from that before inventing a form.
- Render through `App\Support\AuthorMarkdown`, and extend the sanitizer allow-list for the footnote markup.
- The editor needs a custom Tiptap node. Follow the `Callout` node in `resources/js/wysiwyg.js`.
- `EpubExporter` turns the rendered notes into EPUB note links.

## Open questions

- **Number scope.** Are numbers per scene, per chapter or per book? A chapter holds several scenes, and each scene stores its own Markdown.
- **Editor view.** Does the note text show inline, in a popover, or in a list below the scene?
- **Word count.** Count note text with the prose, leave it out, or show it apart?
- **Search and codex references.** Should note text match searches and codex aliases?
