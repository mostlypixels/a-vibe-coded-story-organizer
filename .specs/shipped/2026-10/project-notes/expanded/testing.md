# Testing

Feature tests, `RefreshDatabase`, factories (`NoteFactory`, `NoteCategoryFactory`).

## Notes CRUD — `NoteControllerTest`

- Owner can index, create, store, show, edit, update, destroy.
- Non-owner gets 403 on every action.
- Title required, max 255. Body capped by `AutosavableFields::validationRule('note', 'body')`.
- Category from another project is rejected.
- `create?link=scene:<id>` stores the link; a foreign scene is rejected.
- Index filters: `category`, `category=none`, `linked`, `book` (a note linked to a scene of the
  book matches; a note linked to another book's scene does not), `search`, both sorts.

## Categories — `NoteCategoryControllerTest`

- Store, rename, move. Non-owner 403.
- Sibling name clash rejected, also at root (null parent).
- Move under itself or a descendant rejected. Depth 4 rejected.
- Delete moves notes and sub-categories to the parent; delete at root moves them to root.

## Links — `NoteLinkControllerTest`

- Store and destroy for each `NoteLinkType` case (data provider).
- Target from another project → validation error, no row.
- Duplicate link is a no-op, not a 500.
- Non-owner 403 on note side.
- `link-candidates` and `notes.candidates` return only this project's rows.

## Lifecycle — `NoteLifecycleTest`

- Delete a scene, event, plotline, codex entry: its `notables` rows go, the note stays.
- Delete a book, an act, a chapter: `notables` rows of every cascaded child go. This is the
  test that fails without the `Notable::deleteFor()` calls.
- Delete a project: notes, categories, links all go.
- Delete a note: its links and revisions go.

## Starter categories

- `projects.store` and onboarding create the six names.
- Archive import creates only the archive's categories (none extra).

## Migration — `MoveSceneNotesToNotesTest`

- A scene with notes becomes one note with the expected title and body, linked to the scene.
- A blank or null scene note creates nothing.
- `scene.notes` revisions are gone; other scene revisions stay.

## Revisions and search

- Note body autosave records a revision; History page loads for a note.
- Registry agreement tests still pass with `note.body` added and `scene.notes` removed.
- Project search finds a note by body text and by title; scenes no longer match on notes.

## Export and import

- Round trip: categories (nested), notes, links to each type survive with remapped ids.
- An old archive with a scene `notes_file` imports as a linked note.
- Notes are absent from the `books/` layer and from EPUB output.

## Rendering

- `NoteOutline`: anchors are unique for repeated headings; stored body is unchanged; the
  Contents card is hidden under 3 headings.
- Shared scene page does not show notes linked to the scene.

## JavaScript

- `resources/js/notes/linkPicker.test.js`: debounced search, pick, post, error state.
