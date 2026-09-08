---
title: AJAX Inline Event Creation — Plan Overview
---

# Plan Overview

Manual. Never itself implemented or moved to `plan/implemented/`.

## Execution order

| # | Task | Purpose |
|---|------|---------|
| 01 | `01-quick-event-endpoint.md` | `createInlineEvent()` returns `?Event`; new `projects.events.quick-store` JSON endpoint. |
| 02 | `02-picker-and-client.md` | Save button in `x-single-event-field`, `quick-event.js`, option injection. Depends on 01. |

## Binding design decisions (do not re-litigate)

Resolved in the grill; recorded in `../resolution-log.md`.

1. **A dedicated endpoint**, not `EventController::store()` with `wantsJson()`. `store()`
   requires `plotlines` and redirects.
2. **The trait creates, and returns the created `Event`.** `resolveInlineEvent(): ?int`
   becomes `createInlineEvent(): ?Event` and drops its `$existingId` argument. The quick
   endpoint needs the row it just made. A caller that wants an id falls back to the selected
   id it already holds (`?->id ?? $validated['event_id'] ?? null`) — reading that row back
   would be a query for nothing.
3. **The parent-form path stays.** `new_*_title` / `new_*_datetime` and
   `createInlineEvent()` remain on `SceneController` and `CodexEntrySaver`. This feature
   is an enhancement, not a replacement.
4. **The server renders the option label.** `DateFormat::date()` in the request locale, the
   same call the Blade `<option>` already makes. The client never formats a date.
5. **Only `select[data-event-picker]` gets the new option.** The attribute timeline's
   "Add period at…" selects are out of scope and stay stale until reload.
6. **A quick-created event that is then abandoned persists.** No cleanup. An event is a
   standalone timeline item.
7. **Each Save button saves its own two fields.** No page-wide save.
8. **No inline create of a bookend**, description, or plotline choice. Main plotline only.

## Core invariants every task must preserve

* **The inline inputs are cleared, not merely hidden, after a success.** A non-empty
  `new_*_title` still posted with the parent form makes a *second* event, because
  a created event wins over the selected id.
* **The quick route is registered before `Route::resource('projects.events', …)`**, or
  `/quick` binds as an `{event}`.
* **Authorization mirrors `ProjectPolicy::update`** in both the controller and the Form
  Request, as `StoreEventRequest` already does.
* **One window definition.** `WithinEventWindow` and `EventWindow::forRegularEvent()` back
  both the quick path and the parent forms.
* **A failed request degrades to today's behaviour** — typed values stay, the section stays
  open, the parent form still carries the fields.
