---
status: shipped
shipped: 2026-10-22
planned: 2026-10-22
expanded: 2026-10-22
---

# Write Panel Refresh

The MCP tools change notes and codex entries while the Write page is open, for example when a scene review saves a review note. The Codex and Notes sections show what the page loaded, so the writer must reload the whole page to see the change.

## Goals

- Refresh the Codex and Notes sections when the page gets focus back (window `focus`, `visibilitychange`). The writer returns from the terminal, and the sections are current.
- A refresh icon in each section header, for a manual refresh.
- Render the whole card list again, not only the card bodies. A note newly linked to the scene must appear (`EditorCards::noteCards()` adds linked notes).
- Keep each card's open, collapsed or closed state. The state is stored on the server.
- Replace the cards only when the new HTML is different, so focus and scroll do not move for nothing.

## Non-goals

- Websockets, Reverb or server-sent events. The o2switch hosting cannot run a long-lived process, and SSE holds a PHP worker per open page.
- Timed polling. Focus and the icon cover the terminal-and-browser workflow.
- Refreshing the scene text. Autosave and its 409 conflict choice already guard it.
- Showing what changed, or a "changed elsewhere" badge.

## Rough approach

- One endpoint per section that renders its cards through the same Blade partials as the page load (`editor/partials/card-section`, `codex-card`, `note-card`).
- `resources/js/editor/cards.js` fetches on focus or click and swaps the cards container.
- Follow the focus listeners in `resources/js/autosave/field.js` and the URL templates that `cards.js` already gets from the server.

## Open questions

- A card-state save or a search pick is still running at refresh time: skip the refresh, or run it after?
- The search field has a query or results open: refresh behind it, or wait?
- One endpoint for both sections, or one per section?
- Do many quick focus changes need a debounce?
