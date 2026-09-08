# AJAX inline event creation — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

> [!IMPORTANT]
> An **exception log, not a work journal**. A task that went to plan gets no entry — the
> diff and the task file already record what was built. Bullets under the headings below,
> root cause first, no per-task sections.

## Feedback & decisions

- `resolveInlineEvent(): ?int` became `createInlineEvent(): ?Event`, without the
  `$existingId` argument. The endpoint needs the created row, and the trait was throwing away
  the object it had just built. Keeping `$existingId` in the signature forced an
  `Event::find()` to satisfy the `?Event` return — a query per scene save, and two per codex
  save, to read back an id the caller already had. The trait now owns only the *new* event;
  callers fall back with `?->id ?? $validated[<key>] ?? null`.
- The component resolves its own project with `RouteContext::resolve(request())->project`,
  so the four call sites stay unchanged as the plan asks. Two of them sit on a shallow route
  (`/scenes/{scene}/edit`, `/codex/{codexEntry}/edit`) and carry no `{project}` parameter;
  `RouteContext` is the walk the navigation already uses for the same question. The Save
  button is omitted when that walk finds no project.
- Only `select[data-event-picker]` receives the new option. The codex edit page also carries
  "Add period at…" selects fed by all events; a quick-created event is legal there, but the
  attribute timeline is not this feature. Those selects stay stale until reload.

## Deviations from the spec/plan

- `date-field.js` gained a `clear()` method, outside task 02's stated scope. The core
  invariant is that a success *clears* the inline inputs, and the date picker posts through
  a hidden input driven by separate year/month/day Alpine state — writing the hidden input
  alone leaves the visible boxes filled and Alpine ready to recompose the value. The module
  clears the hidden input and dispatches `quick-event-clear`, which the Blade wires to
  `clear()` on the field's own Alpine scope.
- Field errors render into an *empty twin* of the `x-input-error` slot, not into the slot
  itself. `x-input-error` renders no element at all when it has no messages, so there is
  nothing for the client to write into. The twin `<ul>` carries the same classes, so a
  client-side and a server-side error look identical.
- The status line sits outside the collapsible section, not under the buttons as the plan
  drew it. See the issue below.

## Issues → resolutions

- **A success announced nothing.** The status line was inside the `x-show="newEvent"` block,
  which a success collapses — so the created title was written into an element that was
  hidden in the same tick, and `aria-live` on a hidden element says nothing. Moved the line
  out of the collapsible section, under the picker. Caught in the browser; the JS tests were
  green throughout, because they render no Alpine.
- **Focus could not return to the picker.** The picker is `x-bind:disabled="newEvent"`, and
  Alpine only re-enables it a tick after the button's `.then` sets `newEvent = false`.
  `focus()` on a disabled element is a no-op. The module now sets `owner.disabled = false`
  itself before focusing; Alpine's own update lands on the same value.
