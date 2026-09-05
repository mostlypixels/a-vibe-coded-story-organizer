# UI

## `<x-date-field>` — the picker

Follows the `single-event-field` / `x-text-input` component and Alpine patterns already in the
views. One `x-data` block; no external date library.

Layout (order from `LocaleChoice->order`):
- **Year** — `<input type="text" inputmode="numeric">`. A number box, but text+inputmode so
  there is no spinner and no locale grouping; validate digits in Alpine. `min`/`max` years
  derived from the passed `min`/`max` bookend bounds.
- **Month** — `<select>` of 1–12, labels from `DateFormat` month names in the user's locale.
- **Day** — `<input type="text" inputmode="numeric">`, clamped on blur to days-in-month for the
  chosen month + year (leap-aware).
- **Time** — hidden. A `+ Add time` toggle reveals Hour + Minute. 24h → hour `0–23`; 12h →
  hour `1–12` plus an AM/PM select. Collapsed when the value is `00:00` (the midnight edge).

Behavior:
- On mount, parse `value` (`Y-m-d\TH:i`) into segments; empty value = empty segments.
- On any change, recompose `Y-m-d\TH:i` into the hidden `<input name>`; pad month/day/hour.
- Out-of-window year/day: clamp softly and surface the same `x-input-error` slot the inputs use
  today; the server stays authoritative.
- One `<x-input-error :messages="$errors->get($name)">`, unchanged target.

Swap into: `events/create`, `events/edit`, `scenes/create`, `single-event-field` (codex + scene
inline), `challenges/partials/fields`, `revision-picker`, `progress/index`. Each currently
passes `name`/`value`/`min`/`max` — same props.

## `<x-date>` — the display component

Thin wrapper over `DateFormat` so views stop hand-formatting:
- `<x-date :value="$event->event_datetime" />` → locale date.
- `<x-date :value="$event->event_datetime" with-time />` → locale date + time.
- Reads the shared `LocaleChoice` for the current user.

## Appearance form

Add a **Locale** section to `resources/views/admin/appearance/edit.blade.php`, mirroring the
theme/font pickers: a `<select>` over `LocaleChoice::all()`, marking the active slug, posting
`locale` to the existing appearance update route. A live sample date under the select
(`DateFormat::dateTime(now-ish, chosen)`) shows the effect, like the theme preview.
