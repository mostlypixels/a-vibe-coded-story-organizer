---
title: "Task 02 — Picker button and client module"
---

# Task 02 — Picker button and client module

## Scope

`resources/views/components/single-event-field.blade.php` and a new
`resources/js/quick-event.js` with its test. All four call sites
(`codex/partials/fields.blade.php` ×2, `scenes/create.blade.php`,
`scenes/edit.blade.php`) stay unchanged.

Does **not** change the endpoint, the Form Request, or the parent-form keys — task 01 and
the fallback own those.

## Depends on

Task 01.

## Key decisions already made

* The picker gains `data-event-picker`; each `<option>` gains `data-datetime` carrying the
  event's `Y-m-d\TH:i`, so the client can sort without parsing a display label.
* A **Save event** button beside "Cancel new event", primary weight, disabled in flight.
* The caveat becomes *"The event joins the Main plotline."*
* A status line under the buttons, `aria-live="polite"`: the created title on success, one
  sentence on failure.
* `quick-event.js` exports one function and posts with `window.axios` (already armed with
  the CSRF header in `bootstrap.js`). Same shape as `scene-reorder.js`, with
  `quick-event.test.js` beside it.
* On success: insert the option into **every** `select[data-event-picker]` on the page,
  ordered by `data-datetime` (ties append — the new event has the highest id, matching the
  server's `event_datetime, id` order); select it only in the picker that owns the button;
  clear both inputs; collapse the section; return focus to the picker.
* On 422: write messages into the existing `x-input-error` slots and leave the section open.
* On any other failure: leave the typed values in place.
* `x-single-event-field` keeps its Alpine `x-data`; the button calls into the module.

Detail: `expanded/ui.md`, `expanded/architecture.md` → *The client*.

## Tests to add

`resources/js/quick-event.test.js`:

* Inserts the option into every `select[data-event-picker]`, once each.
* Orders by `data-datetime`, not by append.
* Selects it only in the owning picker.
* Clears both inputs on success — the guard against the parent form making a duplicate.
* A 422 writes field messages and leaves the section open.
* A network failure leaves the typed values in place.
