# Overview

## Problem

To write, a writer now opens Story → Scenes → a scene → Edit, then scrolls past chapter, event, title and status fields to reach the prose. Research means a second tab. The editor page puts the prose first and the research next to it.

This is the daily writing loop. See the November deadline: it must work well by mid-October 2026.

## Goals

- One nav link ("Write") to the right of Dashboard opens the last edited scene.
- Switch scene from a recent list or a search, in the left panel.
- Search the codex and notes in the right panel. Open results as cards next to the prose.
- Prose autosaves with revisions, same as the scene edit page.
- Panel widths and collapsed states persist on the user account.

## Non-goals

- Editing title, status, chapter, event or description. The full edit page keeps them. A link goes there.
- Editing codex entries or notes from a card. Cards are read-only, with a link to the full page.
- Creating or moving scenes.
- Multi-user anything.

## User stories

- As a writer, I click "Write" and I am in my last scene, cursor ready.
- As a writer, I type "store" in the codex search, pick "The Store", and read its description and attributes as of this scene's event.
- As a writer, I hide both panels to write without distraction, and they stay hidden tomorrow.
- As a writer, I drag the right panel wider, and it stays that wide on my other computer.

## Acceptance criteria

- "Write" shows in the project menu (desktop and responsive) right after Dashboard. It is active on editor pages.
- `/projects/{project}/write` redirects to the newest-`updated_at` scene of the project. With no scene, it shows an empty state with a link to create one.
- `/scenes/{scene}/write` shows three columns: left panel, editor, right panel.
- Left panel: recent scenes of the project, newest first, and a search field. A pick loads that scene in the editor.
- Right panel: Codex and Notes sections, each collapsible, each a search field by default. A pick adds a card. A card collapses, expands and closes with an `×`.
- A codex card shows attributes resolved as of the scene's "happens during" event.
- The editor is `x-autosave-field` for `scene.contents`: toolbar, autosave, History link, word count, tab lock, conflict handling.
- Each side panel collapses and resizes with mouse and keyboard. Widths and collapsed states (side panels and right sections) save to the user and load on every editor page.
- Non-owners get 403 on every new route.
