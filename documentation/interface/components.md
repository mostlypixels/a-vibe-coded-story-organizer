# Components

[Documentation](../README.md) › [Interface](README.md) › Components

Reuse a component before adding local Blade and Tailwind markup.

## Conventions

- Components accept content through slots.
- `$attributes->merge()` combines caller classes with defaults.
- Variants use semantic names such as `primary`, `danger`, and `neutral`.
- Keep complete Tailwind class strings in source so the scanner can find them.
- Interactive components support keyboard input and meaningful labels.

## Page structure

| Component | Purpose |
| --- | --- |
| `x-app-layout` | Authenticated shell |
| `x-admin-layout` | Configuration shell and sidebar |
| `x-edit-layout` | Main edit form with action sidebar |
| `x-revisions-layout` | Revisions browser shell |
| `x-page-heading` | Page-level heading and optional actions |
| `x-breadcrumbs` | Route-context trail |

## Content

| Component | Purpose |
| --- | --- |
| `x-heading` | Semantic `h1`–`h6` with a shared scale |
| `x-card` | Surface with optional header and footer |
| `x-table` | Responsive table shell |
| `x-table-heading`, `x-sortable-header` | Static or sortable table header |
| `x-table-row`, `x-table-empty` | Standard rows and empty states; `highlighted` marks a jumped-to group |
| `x-badge`, `x-scene-status-badge` | Compact state labels |
| `x-alert` | Contextual feedback |
| `x-word-count` | Shared count formatting |
| `x-pagination-bar` | Row range, page links and the rows-per-page control under a list; optional `range` prints the page's group span above it |
| `x-row-range` | The "Showing x-y of z" line, on its own for pages with no size control |

## Forms and actions

| Component | Purpose |
| --- | --- |
| `x-text-input`, `x-textarea`, `x-select` | Standard controls |
| `x-input-label`, `x-input-error` | Labels and validation errors |
| `x-button` | Link or button with semantic variants |
| `x-icon-button` | Compact icon action |
| `x-edit-actions`, `x-create-actions` | Standard form actions |
| `x-delete-button` | Labeled delete action |
| `x-delete-with-move-dialog` | Delete or reparent children |
| `x-chip-picker` | Searchable multi-select |
| `x-event-picker`, `x-tag-picker` | Domain wrappers around the chip picker |

## Overlays

| Component | Purpose |
| --- | --- |
| `x-dropdown` | Anchored menu |
| `x-popover` | Small contextual panel |
| `x-disclosure-button` | Trigger button for `x-dropdown` and `x-popover` |
| `x-navigation.menu-link` | Nav section: a link to the hub page plus a chevron for its menu |
| `x-tooltip` | Hover and focus hint |
| `x-dialog` | Application dialog built on `x-modal` |
| `x-modal` | Low-level focus-trapped modal shell |

Put an `x-disclosure-button` in the `trigger` slot of `x-dropdown` or `x-popover`. It reads the panel id through `@aware` and sets `aria-expanded` and `aria-controls`. A plain `<button>` there gets neither.

> [!NOTE]
> A one-off toggle outside these components needs the same attributes by hand: a static `aria-expanded` for the first render, an Alpine `:aria-expanded` binding, and an `aria-controls` that names a unique panel id.

`x-dropdown` behaviour (Alpine `dropdown` in `resources/js/dropdown.js`):

- Escape closes an open dropdown. With focus inside, focus goes back to its disclosure button, and an `x-modal` around it stays open. With focus elsewhere, focus does not move.
- One dropdown is open at a time. Opening one closes the others.
- `hover` (default `false`) opens the menu on mouse hover and closes it 250 ms after the pointer leaves the trigger and the panel. Touch and pen do not open it. In this mode only the disclosure button toggles on click, so a link in the trigger navigates.
- In `hover` mode a click on the disclosure button toggles the menu. A menu opened by a click stays open when the pointer leaves. A click on an open menu closes it, also after a hover-open: the `x-navigation.menu-link` chevron points up while the menu is open, and the click does what the arrow shows.

`x-navigation.menu-link` goes in the `trigger` slot of `x-dropdown hover`. Props: `href`, `active` (section underline, `data-active` on the link), `current` (`aria-current="page"`, the hub page only) and `menuLabel` (the chevron's accessible name, for example "Story menu"). The chevron is the full nav height and at least 24px wide.

`x-modal` owns focus trapping, Escape handling, scroll locking, and its scrim. Use `x-dialog` for normal confirmation and message dialogs.

The modal panel has `role="dialog"` and `aria-modal="true"`. A screen reader reads its name from `aria-labelledby`:

- `x-dialog` with a `title` sets the id and the link itself.
- Without a `title`, give your heading an id and pass it as `labelledby`.

## Rich text

| Component | Purpose |
| --- | --- |
| `x-wysiwyg` | Tiptap editor with textarea fallback |
| `x-rich-text` | Render stored sanitized HTML |
| `x-rich-text-excerpt` | Escaped plain-text excerpt |
| `x-autosave-field` | Editor, history link, status, and word count |

See [Rich text](../features/rich-text.md) for security and serialization rules.

## Related documentation

- [Themes](themes.md)
- [Fonts](fonts.md)
- [Code style](../development/code-style.md)
