# Overview

A codex entry has no read page. `codex.edit` is the only member route, so every path that
names an entry — the type lists, search results, the scene reference sidebar, the
"codex as of this scene" panel — opens a form.

The form is `codex/partials/fields.blade.php`, 310 lines: name input focused on load, a
WYSIWYG description, alias and tag editors, media uploaders, and one section per project
attribute whether or not the entry has a value for it. A writer checking a fact reads it out
of a live form, with the answer among empty inputs.

Two costs beyond the reading itself:

- Accidental edits. The description autosaves (`x-autosave-field`), so an entry opened to be
  read can be changed by a stray keystroke.
- Read-only content has nowhere to live. "Referenced in scenes" sits at the bottom of the form
  because there is no other page for it.

## Goals

- `codex.show`: one screen, no inputs, that answers "what do I know about this".
- Every link that names an entry goes there. Edit stays reachable by an explicit action.
- Show only what is set. An attribute with no value does not appear.
- `CodexEntryController::edit()` and `show()` build the shared read data through the same
  class, not two copies.

## Non-goals

- The edit form keeps its current contents. Hiding its empty attributes is a separate feature.
- No read page for scenes, events or plotlines. Prove the shape here first.
- No public or shareable URL. Same owner-only page as the rest of the project.
- No new history surface. Link to the existing `revisions.index`.

## User stories

- Look a fact up mid-chapter and get back to writing, without a form in the way.
- Open an entry from search and see which scenes it appears in, in story order.
- Reach the edit form from the read page in one click, on purpose.

## Acceptance criteria

- `GET /codex/{codexEntry}` renders name, aliases, tags, description, set attribute values,
  lifespan, and referencing scenes.
- An attribute with no value for the entry is absent from the page.
- The page contains no `<input>`, `<textarea>` or autosave field.
- A non-owner gets 403.
- The codex list, search results, the scene sidebar and the as-of panel link to `codex.show`.
