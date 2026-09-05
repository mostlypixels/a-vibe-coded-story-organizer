---
status: draft
---

# AJAX inline event creation

Today the inline "+ New event" fields (scene edit, codex Existence card) only persist when the
whole parent form saves. The event is created and linked in one round-trip, but nothing on the
page says so, and the new event does not appear until reload.

Make event creation its own action.

## Goal

- Add a "Save event" button beside the inline fields that creates the event over AJAX, without
  saving the parent entry or scene.
- On success, insert the new event into every event `<select>` on the page (both Born and Died,
  or the scene picker), select it in the active field, and collapse the inputs.

## Approach

- One JSON endpoint that mirrors `CreatesInlineEvents::resolveInlineEvent()`: create the event,
  attach the Main plotline, return `{id, title, datetime}`. Either `projects.events.store` answering
  `wantsJson()`, or a dedicated `events.quick-store`.
- Reuse the `WithinEventWindow` rule; return validation errors as JSON and show them inline.
- Alpine posts with the form CSRF token, injects the `<option>`, and clears the inputs.

## Notes

- Removes the "created when you save this page" caveat.
- A created-then-abandoned event persists — acceptable, events are standalone timeline items.
