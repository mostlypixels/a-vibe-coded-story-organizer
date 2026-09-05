# Testing

## Unit — `LocaleChoice`

- `resolve(null)` and `resolve('xx-unknown')` return the config default; never throw.
- `resolve('fr')` returns clock 24, order `dmy`, carbon `fr`.
- `all()` keys match `config('locales.supported')`.

## Unit — `DateFormat`

- `en` → `Mar 15, 1247` and `Mar 15, 1247, 2:30 PM`.
- `fr` → `15 mars 1247` and `15 mars 1247, 14:30` (24h, French month, dmy order).
- No timezone shift: a `Carbon` at `1247-03-15 14:30` formats as `14:30` regardless of the
  user's `timezone` column.
- Midnight formats without implying a set time only where the caller asks for date-only.

## Feature — appearance

- Owner can set `locale` to a supported slug; it persists (extends the existing appearance test).
- An unsupported `locale` is rejected (422) by `UpdateAppearanceRequest`.
- `locale = null` leaves the user on the default with no error.

## Feature — picker render (the #132-class guard)

- `events/create` and `events/edit` render `<x-date-field>` and still emit **one** hidden input
  named `event_datetime`. Assert the emitted name, so a future refactor can't silently break the
  save contract (the mismatch bug that shipped green before).
- The inline codex/scene pickers still emit `new_inception_event_title` etc. — keep the #132
  render guards passing after the swap.

## Feature — save contract unchanged

- Posting through the fields a user would fill (year/month/day/time) saves the identical
  `event_datetime` a native input produced. Reuse existing event/codex save tests; they post the
  composed value, so they must stay green with no change.
- Out-of-window date still 422s via `WithinEventWindow`.

## Display

- An event date renders through `<x-date>` in the chosen locale on `events/index` and the codex
  as-of panel (assert the localized string for a `fr` user).
