# Entity links — testing

## Unit

- `EntityLinks` parse, rewrite and strip, in HTML and in Markdown: several links, nested marks, a link-like text that is not a link.
- Sanitizer, both profiles: keeps `entity:scene/42`. Removes `entity:unknown/1`, `entity:scene/abc`, `entity:scene/1?x`, `entity://host/1`.
- `LinkTargetType` rename: archive values unchanged (extend `NoteLinkTypeTest`).

## Feature

- Render: resolved link → route URL. Deleted target → missing span. Other project's id → missing span, and no title in the output.
- Render cost: one query per type, whatever the number of links. Assert the query count.
- Index: save a note with two links → two rows. Edit to one → one row. Delete the source → no rows. Delete the target → no rows.
- Index through every save path: form update, autosave (`FieldAutosaver`), scene save, codex save, revision revert.
- Cascade: delete a chapter → rows for its scenes as source and target are gone.
- Import: links in a note, a codex description and scene Markdown point at the new ids. A dangling id becomes plain text. A resumed import after a failure in the Links phase gives the same result.
- Export then import round trip of the Melusine seed with links.
- EPUB and static site: link text present, no `entity:` in the output.
- Picker endpoint: owner gets candidates, non-owner gets 403, unknown type gets 422.
- Backlinks card: lists sources, hidden when empty.

## JavaScript (Vitest)

- Tiptap accepts and keeps `entity:` hrefs in HTML and Markdown mode (round trip).
- Picker sets the href on a selection and inserts the label with no selection.
