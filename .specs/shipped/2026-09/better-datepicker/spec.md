---
status: shipped
shipped: 2026-09-05
planned: 2026-08-24
expanded: 2026-08-24
---

# Better datepicker

## Problem

Every date on the app is a native `datetime-local` input (event create/edit, scene create,
the codex and scene inline event pickers, challenges, revision-picker, progress). Its year
segment prefills `0000` and left-shifts as you type, so a four-digit year reads `0001 0012
0123 1234` on the way in. This app is a fiction timeline: years are arbitrary (medieval,
far-future, fantasy) and span a wide window, so year entry is the common case, and the native
control does it worst. Authors cannot fix it — the browser owns that segment.

## Goals

### The picker

- Replace the native control with one shared component used in all seven date inputs.
- Enter the year in a plain number box: type `1247`, no digit-shift, no leading zeros.
- Month as a localized-name select; day as a number box, clamped to the month and year
  (Feb → 28/29) on blur.
- Field order (day/month/year) follows the user's locale.
- Time is optional and hidden. An "Add time" control reveals hour and minute, in the user's
  12h or 24h clock.
- Keep the server contract unchanged: the component composes its parts into a hidden input
  that posts the same `event_datetime` string as today, so no controller, Form Request, or
  `WithinEventWindow` change is needed.
- Bound the picker to the project's Start and End fixed (bookend) events. The controllers
  already pass these as `windowMin`/`windowMax` (`startEvent()`/`endEvent()`, and
  `EventController::datetimeBounds` for the bookend-edit case); the component clamps the year
  and day to them. These vary per project — not a literal range. The server stays
  authoritative through `WithinEventWindow`; the boxes only hint `min`/`max`.

### The locale preference

- Add a per-user `locale` preference, set in profile settings. Follow the existing preference
  pattern: a column on `users`, a `resolve()` helper like `ThemePreset`/`FontChoice`, and the
  appearance form (`UpdateAppearanceRequest`, the profile appearance partial).
- The locale drives date/time formatting: field order and month names in the picker, the 12h
  vs 24h clock, and the format of every date the app *displays* (as-of panel, event lists,
  timelines). One shared formatter reads the resolved locale so input and display always agree.
- Month names and localized formatting come from PHP `Intl`/Carbon locale data, not from app
  translation files — so this needs no interface-string translation work.
- Default the locale from the app locale (`en`) so existing users are unaffected until they
  choose one.

## Non-goals

- No full interface translation (i18n of UI strings). The locale here affects date and time
  formatting only.
- No new `has_time` column. The schema has no "time was set" flag, so "no time" is stored as
  `00:00`. An event genuinely at midnight reopens with the time section collapsed and looks
  time-less — an accepted edge, not worth a domain change pre-V1.
- No BC / year-zero support. The bookend window sits in the positive (AD) range. Negative or
  era-qualified years are a separate future spec.
- No calendar popover, no relative "pick a date near today" affordance — this is timeline
  entry, not scheduling.
- No change to how events are ordered or stored — display and input formatting only.

## Rough approach

- **Picker:** a Blade component (follow the `single-event-field` / `x-text-input` component
  and Alpine patterns already in the views) that takes the same `name`, `value`, and
  `min`/`max` the current inputs pass, parses an incoming `Y-m-d\TH:i` value into segments,
  and writes a hidden input of the same name on every change. Swap it into the seven call
  sites.
- **Locale:** a `users.locale` column with a resolver, edited in the appearance settings, and
  a shared date/time formatter that both the picker and every display site read.

## Open ends

- Which locales to offer, and whether the picker's field order and clock come from parsing the
  locale's ICU pattern or from a small explicit map — decide during planning.
- Graceful behavior with JS disabled — the app already leans on Alpine everywhere, so a
  no-JS fallback is probably out of scope, but confirm during planning.
