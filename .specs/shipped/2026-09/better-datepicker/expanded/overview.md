# Overview

## Problem

Two coupled problems, one feature:

1. **The picker.** All date entry is native `datetime-local`. Its year segment prefills `0000`
   and left-shifts on input, so a four-digit year reads `0001 0012 0123 1234`. In a fiction
   timeline, arbitrary years are the common case, so this is the worst-hit field. The browser
   owns the segment; authors cannot fix it.
2. **The format.** Date display is hardcoded (`->format('M j, Y g:i A')` and friends) in every
   view — a single US-style, 12h format with no user control.

Both are solved by the same lever: a per-user **locale** that drives how dates are entered and
shown, plus a control that reads it.

## Goals

- One shared date-entry component, used in all seven `datetime-local` sites, with a plain
  number year box (no digit-shift), localized month select, clamped day box, and optional
  time in the user's clock.
- A per-user `locale` preference in appearance settings, driving field order, month names,
  12h/24h clock, and every date the app displays.
- One shared formatter both the picker and display sites read, so input and output always agree.
- No server-contract change to event saving: the component still posts the same
  `event_datetime` string.

## Non-goals

- No interface translation (i18n of UI strings). Locale affects date/time formatting only.
- No `has_time` column; `00:00` means "no time" (midnight events read as time-less — accepted).
- No BC / year-zero support (separate future spec).
- No timezone shift of event dates — they are in-universe fictional datetimes, not real-world
  moments. Locale changes *formatting*, never the instant. (User `timezone` stays a real-world
  concern for word-count/challenges only.)
- No calendar popover.

## User stories

- As an author, I type `1247` into a year box in one motion and it stays `1247`.
- As a French author, I set my locale to Français and every date shows `15 mars 1247, 14:30`.
- As an author who never opens settings, dates keep working in English 12h — nothing forced.

## Acceptance criteria

- Typing four digits into the year box yields that year, no leading-zero shifting.
- The day box rejects/clamps an out-of-range day for the chosen month and year (Feb → 28/29).
- The picker clamps to the project's Start/End bookend window; the server still rejects
  out-of-window dates via `WithinEventWindow`.
- Saving through the new component produces the identical `event_datetime` a native input would.
- Setting locale to `fr`/`it` changes month names, field order, and clock in both the picker
  and every date display; `en` restores US style.
- A user with `locale = null` sees the app default (`en`), unchanged from today.
