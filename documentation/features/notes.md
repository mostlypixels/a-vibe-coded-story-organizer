# Notes

[Documentation](../README.md) › [Features](README.md) › Notes

A note is a titled, long rich-text page that is about the story, not in it: research, a synopsis, continuity problems, decisions.

## Notes or codex?

Ask: does the name appear in a scene?

- **Yes** → a [codex](codex.md) entry. People, places, groups. It has aliases and the scene matcher links it.
- **No** → a note. Research, outline, to-dos.
- **Both** (a real street you researched) → a codex entry for the street, a note for the research, and a link between them.

A note has no aliases, matcher, attributes, or timeline. Do not add them: a note would become a weaker codex.

## Model

| Table | Holds |
| --- | --- |
| `notes` | `project_id`, `note_category_id` (nullable), `title`, `body` |
| `note_categories` | `project_id`, `parent_id` (nullable), `name` |
| `notables` | One link: `note_id`, `notable_type`, `notable_id` |

- `body` is rich HTML ([Rich text](rich-text.md)). It autosaves and has [History](revisions.md). The cap is `note.body` = 500 000 characters in `config/revisions.php`. The title has no autosave.
- A note never moves between projects.
- A note is in one category or none.

## Links

A note links to zero or more books, acts, chapters, scenes, events, plotlines, and codex entries of the **same** project. `App\Enums\NoteLinkType` lists the kinds. Routes, validation, the picker, and the archive read it.

- Resolve a target with `NoteLinkType::queryFor($project)`. Never trust a raw id: a guessed id must not link across projects.
- Each linkable type shows a **Notes** card (`x-notes-card`) on its read and edit pages, with "New note" and "Link a note".
- `notes.create?link=scene:12` pre-fills one link.
- `Note::linkTo()` links twice as a no-op.

### Deleting a linked entity

`notables` has no foreign key on the target, because the target is polymorphic. Deleting the target must delete its link rows. The note stays.

- `HasNotes` removes the links of one deleted entity in a `deleted` hook.
- A database cascade fires no model events.

> [!WARNING]
> Book, Act, and Chapter delete their children with a cascade. Their `deleting` hooks call `Notable::deleteFor()` for the cascaded children, next to `Revision::deleteFor()`. A new parent that cascades to a linkable type must do the same, or dead link rows remain.

## Categories

- A category nests up to `NoteCategory::MAX_DEPTH` (3) levels. `ValidNoteCategoryParent` checks the depth of the new parent and the height of the moved subtree on the server.
- Categories sort by name. The notes index shows the tree beside the list.
- A category filter shows only the notes directly in that category.
- **Move-up rule:** deleting a category moves its notes and sub-categories to its parent, or to the root. `NoteCategoryDeleter` does this in one transaction. Nothing is lost.

### Starter categories

Planning, Research, Continuity, Plot threads, Publishing, Ideas.

- `StarterNoteCategories::createFor()` creates them. Project create, onboarding, and the demo installer call it.
- It is not a `Project::created` hook. A project is valid with no categories, and an archive import must create only the archive's own categories.

## Index, search, and read view

- The index filters by category, linked type, book, and title text. A book filter matches notes linked to the book or to an act, chapter, or scene inside it.
- Project search finds notes by title and body, in the Notes section. A book filter hides notes.
- The read page shows a **Contents** card when the body has 3 or more headings. `App\Support\NoteOutline` adds the anchors at render time. The stored HTML does not change.

## Export and import

- Notes never reach EPUB, the `books/` layer, or a shared scene.
- The archive carries notes, categories, and links in `data/notes/`. See [Archive format](../export-import/archive-format.md#the-notes-branch).

## Scene notes

The old `scenes.notes` column no longer exists. A migration turned each non-empty value into a note titled `Notes: <scene name>`, in no category, linked to the scene.

- Duplicating a scene or a codex entry links the copy to the same notes. No note is copied.
- Only these two types are duplicable.
