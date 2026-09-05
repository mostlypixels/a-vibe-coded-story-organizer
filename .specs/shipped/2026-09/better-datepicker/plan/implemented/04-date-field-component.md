# 04 — Date-field component

## Scope

- Build `<x-date-field>` (Alpine, no external library). Props `name`, `value` (`Y-m-d\TH:i` or
  empty), `min`, `max`, `required`.
  - Segments: Year (`text` + `inputmode=numeric`), Month (`<select>` 1–12, labels from the
    locale's month names), Day (`text` + `inputmode=numeric`, clamped on blur to days-in-month,
    leap-aware). Optional Time behind an `+ Add time` toggle: 24h → hour 0–23; 12h → hour 1–12 +
    AM/PM. Collapsed when value time is `00:00`.
  - **Segment order and the 12/24h choice come from the locale's ICU pattern** (`IntlDateFormatter`
    for the shared `LocaleChoice`).
  - Parse `value` into segments on mount; recompose `Y-m-d\TH:i` into **one hidden
    `<input name="{name}">`** on every change (pad month/day/hour). Year/day clamp softly to the
    `min`/`max` bookend bounds; keep the existing `<x-input-error :messages="$errors->get($name)">`.
- Adopt it in `events/create.blade.php` and `events/edit.blade.php` (replace the two
  `datetime-local` inputs).

Not in scope: the other five sites (task 05). No server-side change.

## Depends on

- 01 (locale for order/clock/month names). Independent of 02/03.

## Key decisions

- One hidden input, original name, `Y-m-d\TH:i` — the save contract is untouched (invariant).
- Year box is text+inputmode (no spinner, no grouping).
- Derive order/clock from ICU; no config map.

## Consult

- `expanded/ui.md` → `<x-date-field>`; `expanded/architecture.md` → picker contract.

## Tests

- Feature render guard (the #132-class check): `events/create` and `events/edit` emit exactly
  **one** hidden input named `event_datetime`. Assert the name.
- Feature save contract: posting the year/month/day/(time) a user fills saves the identical
  `event_datetime` the native input produced; existing event save tests stay green unchanged.
- Out-of-window date still 422s via `WithinEventWindow`.
- Day-clamp unit/interaction: Feb in a leap vs non-leap year.
