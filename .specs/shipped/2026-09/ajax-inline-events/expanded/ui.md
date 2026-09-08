# UI

All of it in `resources/views/components/single-event-field.blade.php`. The component is
used four times (`codex/partials/fields.blade.php` twice, `scenes/create.blade.php`,
`scenes/edit.blade.php`) and none of those call sites change.

## Changes to the component

- The picker gains `data-event-picker` and each option a `data-datetime`, so the client can
  find every select on the page and keep them in date order.
- A **Save event** button beside "Cancel new event", primary weight, disabled while a
  request is in flight.
- The caveat line — *"The event is created and joins the Main plotline when you save this
  page."* — becomes *"The event joins the Main plotline."* It is still true and no longer
  an apology.
- A status line under the buttons for the result: the created title on success, one
  sentence on failure. `aria-live="polite"`, or a screen-reader user learns nothing.
- Field-level errors render into the existing `x-input-error` slots, so a client-side and a
  server-side error look the same.

## Behaviour

- Success: inputs clear, the section collapses, the picker shows the new event selected.
- The two codex pickers share a page. Creating from Born must leave Died usable, with the
  new event in its list but not selected.
- Keyboard: the button is a real `<button>`, and focus returns to the picker after a
  success. The picker is what the writer was answering.
