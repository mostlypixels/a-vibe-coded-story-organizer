# 05 — Date-field rollout

## Scope

- Swap `<x-date-field>` into the remaining `datetime-local` entry inputs:
  `components/single-event-field.blade.php` (codex + scene inline), `scenes/create.blade.php`,
  `challenges/partials/fields.blade.php`, `components/revision-picker.blade.php`,
  `progress/index.blade.php`.
- For each, confirm it is a genuine date-**entry** field before swapping; a read-only or filter
  use needs no picker (note it and skip).
- In `single-event-field`, the picker replaces only the datetime sub-input. **Preserve the #132
  field names** (`new_inception_event_title`, etc.) — the picker's hidden input keeps the field's
  original name.

Not in scope: the event forms (task 04). No server-side change.

## Depends on

- 04 (the component).

## Key decisions

- Same one-hidden-input contract per site; names unchanged (invariant).
- These sites' date **display** is not locale-migrated here (task 02 covered event-date display
  only; real-world dates stay as-is) — a known, accepted mixed state.

## Consult

- `expanded/ui.md` → swap list; `00-overview.md` → invariants.

## Tests

- The existing #132 render guards (codex + scene inline) stay green after the swap.
- Each swapped site still saves its date correctly (reuse/extend existing save tests for
  scenes, challenges, revisions).
