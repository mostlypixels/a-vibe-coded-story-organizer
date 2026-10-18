# UI

## Views

| View | Contents |
| --- | --- |
| `resources/views/editor/show.blade.php` | `x-app-layout`, then the three-column grid |
| `resources/views/editor/empty.blade.php` | No scenes yet: `x-table-empty`-style message and a link to create a scene |
| `resources/views/editor/partials/recent-scenes.blade.php` | Left panel list |
| `resources/views/editor/partials/codex-card.blade.php` | Card body for a codex entry |
| `resources/views/editor/partials/note-card.blade.php` | Card body for a note |
| `resources/views/components/editor-panel.blade.php` | One side panel: header with collapse button, slot, resize handle |
| `resources/views/components/editor-card.blade.php` | One card: title, type, collapse, `×`, link to full page |

## Layout

- Full window width. Ignore `page_width`: the panels need the room. Height: viewport minus the nav, each column scrolls alone.
- CSS grid: `grid-template-columns: var(--left) 1fr var(--right)`. Alpine sets the variables. A collapsed panel becomes a 40 px rail with an expand button.
- Prose column uses the manuscript font, scale and leading (`FontChoice::resolve()`), and a readable max width (`max-w-prose`), centred.
- Below `lg`: both panels start collapsed, and an expanded one opens as an overlay drawer over the prose. Stored widths do not apply. See `open-questions.md`.

## Left panel

- Search field (`role="combobox"`), results list under it. Type `scene` on `projects.link-targets`.
- Empty search: recent scenes from `RecentlyEdited::scenes()` with `x-recent-list` style rows: name, context segments (book, act, chapter), relative time.
- The current scene row has `aria-current="page"`.

## Centre

- Scene name as `h1`, context line under it, an `x-icon-edit-link` to `scenes.edit`.
- `x-autosave-field` for contents, with toolbar from `x-wysiwyg`. Status badge and word count come with it.
- Focus the editor on load.

## Right panel

- Two `x-collapsible-card`-style sections, Codex and Notes. Use `<details>` like that component, but the open state must report to `editorLayout`, so listen to `toggle`.
- Each section: search field, result list, then its cards, newest on top.
- Codex card: type badge, cover thumbnail if any, description (`x-rich-text`), attribute rows as of the scene's event. Unassigned scene: the "undetermined" state of `codex/partials/as-of.blade.php`. Reuse that partial's row markup.
- Note card: category, body (`x-rich-text`).
- Card header: `x-disclosure-button`-style collapse toggle with `aria-expanded`, `x-icon-close-button` (`×`, label "Close {name}"), `x-icon-view-link` to the full page in a new tab.

## Resize handle

- `<div role="separator" aria-orientation="vertical" tabindex="0">` with `aria-valuenow/min/max` in px and `aria-label` "Resize left panel".
- Pointer drag (pointer capture). Arrow keys: 16 px. Home/End: min/max. Enter: toggle collapse.
- Double-click resets to the default width.

## Keyboard

- Search results: arrows, Enter, Escape, same as the Link to… dialog (`candidate-search.js`).
- Keyboard shortcuts to toggle panels are out of scope. The `keyboard-shortcuts` draft spec owns them.
