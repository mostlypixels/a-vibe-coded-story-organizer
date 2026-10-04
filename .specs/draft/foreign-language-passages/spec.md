---
status: draft
---

# Foreign-Language Passages

Novels quote other languages: a Latin motto, an English phrase in a French book, a line of dialect. The app knows one language per book (`BookLanguage`). It cannot mark a passage as another language. The result:

- The EPUB hyphenates the passage with the rules of the wrong language.
- Screen readers read it with the wrong voice and pronunciation.
- The browser spell checker flags every word in the passage.

Found while importing *Les Misérables* as a test project. Latin passages appear in 10 chapters and English in 1. The source marks them with a language tag. The import drops the tag and keeps the text, sometimes in italics.

## Goals

- The writer can mark a selection as another language, from the toolbar or the slash menu, and remove the mark.
- The mark is visible in the editor, but quiet: a reader of the draft should not trip over it.
- The rendered HTML carries the language (`lang`, and `xml:lang` in the EPUB) wherever scene prose renders.
- The browser spell checker uses the passage's language or skips it.
- The mark survives the archive round trip and the revision history.

## Non-goals

- Translation or a gloss attached to the passage. A footnote covers that (see `footnotes`).
- Language detection.
- Marks on codex, timeline or book fields. Scene prose only.
- A per-scene or per-chapter language.

## Rough approach

- An inline Tiptap mark with a language attribute, next to the existing marks in `resources/js/wysiwyg.js`.
- Render through `App\Support\AuthorMarkdown`, and extend the sanitizer allow-list for `lang`.
- `EpubExporter` already sets the book language. The passage language rides on the rendered HTML.

## Open questions

- **Markdown form.** Plain Markdown has no inline attributes. Candidates: an allow-listed `<span lang="la">`, or an attribute syntax such as `{lang=la}` (`league/commonmark` has an attributes extension). The same syntax could also carry paragraph alignment and the verse block (see `verse-block`).
- **Language list.** Any BCP-47 code, or a short list? `BookLanguage` holds only the languages the app is proofed in, and Latin is not one of them.
- **Italics.** Should the mark add italics, or leave the style to the writer?
