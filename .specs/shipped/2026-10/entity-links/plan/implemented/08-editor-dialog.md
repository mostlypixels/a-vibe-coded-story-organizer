# 08 — Editor dialog

## Scope

- "Link to…" in the slash menu and the toolbar of `resources/js/wysiwyg.js`. It opens a dialog with a type select, a search box and a result list.
- Move the search, debounce and sequence logic out of `resources/js/notes/linkPicker.js` into a shared module. Both pickers use it.
- With a selection, set `href="entity:<type>/<id>"`. With no selection, insert the label as link text.
- Style entity links in the editor apart from web links. A click does not navigate.
- `x-wysiwyg` gets the endpoint URL from 07. Strings go through `lang/`.
- Update `documentation/features/rich-text.md` → Editor.

## Depends on

- 02, 07.

## Key decisions already made

- No dead-link check in the editor.
- Keyboard: arrows move in results, Enter picks, Escape closes.
- Keep the Tiptap `Editor` in a closure (see the caution in `rich-text.md`).

## Docs

- `expanded/ui.md` → Editor.

## Tests

- Vitest: the picker sets the href on a selection, inserts the label with no selection, and ignores a slow old response.
- Vitest: the existing `linkPicker` tests still pass on the shared module.
- Feature: an editor page renders the endpoint URL for its project.
