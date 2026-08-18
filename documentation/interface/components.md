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
| `x-table-row`, `x-table-empty` | Standard rows and empty states |
| `x-badge`, `x-scene-status-badge` | Compact state labels |
| `x-alert` | Contextual feedback |
| `x-word-count` | Shared count formatting |

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
| `x-tooltip` | Hover and focus hint |
| `x-dialog` | Application dialog built on `x-modal` |
| `x-modal` | Low-level focus-trapped modal shell |

`x-modal` owns focus trapping, Escape handling, scroll locking, and its scrim. Use `x-dialog` for normal confirmation and message dialogs.

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
