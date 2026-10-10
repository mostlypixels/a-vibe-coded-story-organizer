# Overview

## User stories

- I paste a 56 KB synopsis into one note and jump between its chapters with a table of contents.
- I keep 30+ research pages in nested categories and find one in two clicks.
- I link a research note about a street to its codex Location and to the scenes set there.
- On a scene, chapter or codex page I see every note about it, and I can add or link a note there.
- I delete a scene and its notes stay, because they may link to other things.
- My old scene notes are still there after the upgrade, as notes linked to their scenes.

## Acceptance criteria

- **Notes** in the primary project navigation opens the notes index.
- A note has a title (required) and a rich body. The body autosaves and has History.
- A note links to zero or more books, acts, chapters, scenes, events, plotlines and codex entries
  of the **same** project. A link to another project's entity is refused.
- Each of those seven entities shows a Notes card on its read and edit pages, with "New note"
  and "Link a note".
- A note is in one category or none. Categories nest, up to 3 levels.
- A new project gets the six starter categories. An imported project gets the archive's
  categories only.
- Deleting a category moves its notes and sub-categories to its parent.
- Deleting a linked entity removes the link. The note survives.
- The notes index filters by category, linked entity type, book and title text.
- Project search finds notes by title and body.
- Project export writes notes, categories and links. Import restores them.
- The read view of a note shows a table of contents built from its headings.
- `scenes.notes` no longer exists. Each non-empty value became a note linked to its scene.

## Non-goals (beyond `spec.md`)

- No import of a folder of Markdown files (the Gardenia `.ai` folder). Paste works.
- No note duplication action.
- No ordering of notes or categories other than by name and date.
