# Overview

## Problem

`x-single-event-field` offers a picker plus "+ New event" fields. The new event is only
created when the parent form saves, through `CreatesInlineEvents::resolveInlineEvent()`.
Three costs:

- The field says so out loud — *"The event is created and joins the Main plotline when you
  save this page."* A caveat in the interface is a design apology.
- A codex entry has two pickers (Born, Died). Creating an event for one leaves the other
  picker without it until the page reloads.
- On a validation failure anywhere else in the form, the typed event survives only through
  `old()`, and the writer re-reads two fields to check.

## Goals

- A "Save event" button beside the inline fields creates the event on its own, with no
  parent save.
- On success every event `<select>` on the page gains the option, the active field selects
  it, and the inline inputs collapse.
- Validation errors come back inline, from the same rules the form uses.
- The parent form keeps working when JavaScript is off or the request fails.

## Non-goals

- No change to what an inline event is: a regular event on the Main plotline. Plotline
  choice, description and the rest stay on the full create form.
- No editing or deleting an event from the field.
- No change to the timeline, the scene picker's ordering, or `EventWindow`.
- The bookend events (Start, End) are not creatable here. They never were.

## Acceptance criteria

- Saving an event from a scene form persists it, attaches the Main plotline, and leaves the
  scene unsaved.
- Creating from the Born picker puts the option in the Died picker too, in date order.
- A title outside the project window fails with the window message, on the field, and
  creates nothing.
- A non-owner gets 403. A missing title gets 422.
- With `new_event_title` still posted the old way, the parent form behaves exactly as now.
