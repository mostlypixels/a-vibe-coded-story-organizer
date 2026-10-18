---
status: shipped
shipped: 2026-10-18
planned: 2026-10-18
expanded: 2026-10-18
---

# Editor Interface

A specialized interface that allows to load and edit scenes quickly.

It must be linked in the main menu, to the right of "dashboard".

It must have three columns:

* a collapsable left sidebar / panel
* the central editor
* a collapsable right sidebar / panel

The side panels must be resizable, and the sizes and collapsed status must be saved to the user's account so it persists across sessions.

The sidebar panels are used for navigation and research.

## Left Sidebar

The left panel contains a list of the most recently edited scenes, with a search field. Selecting a scene from the list will load it into the editor.

## Right Sidebar

Contains collapsable panels to search through and load data from the rest of the application:

* codex entries and attributes
* notes

By default, the codex and notes panels only display a search field.
Through ajax search, we can display a list of results, and load the selected entry into a card of the current panel.

Ie: we edit the scene "Jane Doe goes to the store".
The user wants information about the store.
The user searches for "store" in the codex.
The user selects the store entry.
The store entry is loaded into the card in the notes panel.

The cards themselves can be collapsed and expanded. They also have a "x" button to close them and remove them from the view.

## Central Editor

Tiptap editor, with a toolbar.
Autosaves with revisions similarly to the full scene edit form.
