# 02 — Date display

## Scope

- `app/Support/DateFormat.php`: the single home for formatting an event `Carbon` in a locale.
  - `date(CarbonInterface, LocaleChoice): string` → `->locale($carbon)->isoFormat('LL')`.
  - `dateTime(CarbonInterface, LocaleChoice): string` → `isoFormat('LLL')` (or `LL` + `LT`).
  - **Never** `setTimezone` — event dates are in-universe instants.
- `<x-date>` component: `:value` (Carbon) + optional `with-time`, reads the shared
  `LocaleChoice`, delegates to `DateFormat`.
- Replace every inline `event_datetime->format(...)` with `<x-date>` / `DateFormat`. Known
  sites: `events/index.blade.php`, `codex/partials/as-of.blade.php`,
  `codex/partials/attribute-timeline.blade.php`, event-picker option labels,
  `single-event-field` option labels. Grep `event_datetime->format` to catch the rest.

Not in scope: real-world date displays (word-count, challenge, progress) — out of scope, keep
current formatting. The date-entry control (04/05).

## Depends on

- 01 (LocaleChoice + shared locale).

## Key decisions

- Display uses Carbon `isoFormat` tokens — order, month names, 12/24h all locale-derived. No map.
- Event dates never timezone-shifted.

## Consult

- `expanded/architecture.md` → DateFormat / display rollout; `expanded/ui.md` → `<x-date>`.

## Tests

- Unit `DateFormat`: `en` → `Mar 15, 1247` / `…, 2:30 PM`; `fr` → `15 mars 1247` / `…, 14:30`;
  identical output regardless of the user's `timezone` column.
- Feature: an event date renders in a `fr` user's locale on `events/index` and the codex as-of
  panel (assert the localized string).
