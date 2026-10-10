# UI

## Navigation

- Top-level **Notes** link in `components/navigation/project-menu.blade.php` and
  `responsive-project-menu.blade.php`, between Codex and Tools. A plain link, no dropdown.
- `ProjectNavigation::$notesActive` on any `notes.*` route.
- Page titles and breadcrumbs: `Notes › <category path> › <title>`.

## Notes index (`notes/index.blade.php`)

- Two columns from `md` up; stacked on phones.
- **Left: category tree.** "All notes", "Uncategorized", then the tree. Each item is a link that
  sets `?category=`. Current item has `aria-current`. A "New category" button opens an
  `x-dialog` (name, parent select).
- Each category has an `x-icon-dialog-button` for rename/move (same dialog) and
  `x-icon-delete-button` with `x-confirm-delete-dialog`. The message says where the contents go.
- **Right: note table.** `x-table` with Title, Category, Links (count), Updated. Filter bar:
  linked type select, book select (only with more than one book), title search. `x-sortable-header`
  on Title and Updated. `x-pagination-bar`.
- Empty state: `x-table-empty` with a "New note" button.

## Note read view (`notes/show.blade.php`)

- Title, category path, `x-icon-edit-link`.
- **Contents** card from `NoteOutline` when the note has 3 or more headings. A list of
  in-page links, nested by heading level.
- Body via `x-rich-text`.
- **Linked to** card: links grouped by type, each to the entity's show route.

## Note create and edit

- `x-edit-layout`, like codex. Fields: title (`x-text-input`), category (`x-select`, options
  indented by depth from `NoteCategoryTree`), body (`x-autosave-field entity="note"
  field="body"` on edit, `x-wysiwyg` on create).
- **Links** card on edit: current links with a remove button each; "Add link" opens a dialog
  with a type select and a search input that reads `link-candidates`. Small Alpine component in
  `resources/js/notes/linkPicker.js` with a Vitest file beside it.
- Actions card: delete, History link (`x-entity-history-link`).

## Notes card on linkable pages

New component `x-notes-card :notes :linkable`.

- Placed on the read and edit pages of book, act, chapter, scene, event, plotline and codex entry.
- Lists title and a body excerpt (`x-rich-text-excerpt`). Each title links to `notes.show`.
- **New note** → `notes.create?link=<type>:<id>`.
- **Link a note** → dialog with a note search. It reads `notes.candidates` and posts the picked
  note to `notes.links.store` with this entity's type and id. Same `linkPicker.js`, other
  direction.
- Unlink button per row, with no confirm. Unlinking loses nothing.
- Controllers eager-load `notes` for these pages.

## Scene edit page

The Notes rich field is removed. The scene's Notes card takes its place in the same column, so
the writer still sees scene notes while writing.
