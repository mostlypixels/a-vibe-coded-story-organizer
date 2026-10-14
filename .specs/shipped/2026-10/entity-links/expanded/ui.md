# Entity links — UI

## Editor (`resources/js/wysiwyg.js`)

- New slash item and toolbar button: **Link to…**. It opens a picker dialog:
  - type select (from `LinkTargetType`), search box, result list;
  - reuse the search, debounce and sequence logic in `resources/js/notes/linkPicker.js`. Extract the shared part only for this second caller.
- With text selected, picking a target sets `href="entity:<type>/<id>"` on the selection. With no selection, it inserts the target's label as the link text.
- The existing **Link** URL dialog stays for web links. It rejects `entity:` typed by hand only if the sanitizer would strip it.
- Keyboard: dialog opens from the slash menu, arrow keys move in results, Enter picks, Escape closes.
- In the editor, entity links get a distinct style (no external-link look). Clicking does not navigate.

## Read views

- A resolved link is a normal link. Style it like an internal link.
- `.entity-link-missing`: struck text with a `title` that says the target is gone. Add the class to `resources/css/app.css`. EPUB does not need it.

## Backlinks

- New card **Linked from** on each target's read page, next to the Notes card (`x-notes-card`). It lists the source title and field, linked to the source.
- Hide the card when it is empty.
- Note read page shows it too, because notes are now targets.
