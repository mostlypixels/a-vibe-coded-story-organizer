# Better datepicker — plan overview

The manual. Never implemented or moved.

## Execution order

| # | Task | Purpose |
|---|------|---------|
| 01 | locale-foundation | `users.locale` column, `config/locales.php`, `LocaleChoice` resolver, share resolved locale to views |
| 02 | date-display | `DateFormat` (Carbon `isoFormat`) + `<x-date>` component; migrate every event-date display to it |
| 03 | appearance-locale-pref | Locale select in appearance settings + validation |
| 04 | date-field-component | Build `<x-date-field>`; adopt in the two event forms with full save-contract tests |
| 05 | date-field-rollout | Swap `<x-date-field>` into the remaining datetime inputs |

Dependencies: 02, 03, 04 each depend only on 01. 05 depends on 04.

## Binding defaults (decided in the grill — do not re-litigate)

- **Derive, don't map.** No clock/order table in config. Display uses Carbon
  `->locale($carbon)->isoFormat('LL'/'LLL'/'LT')`; the picker derives segment order and 12/24h
  from the locale's ICU pattern via `IntlDateFormatter`. `ext-intl` is required and present.
- `config/locales.php` lists **only** offered locales + display names: the **24 official EU
  languages + `en-US`**. Exact codes/endonyms filled at build; drop any Carbon can't localize.
- `LocaleChoice` carries slug, name, Carbon locale code — **no clock/order fields** (thinner
  than `expanded/architecture.md` first described; that doc predates the grill).
- Year input is `type="text" inputmode="numeric"`, Alpine-validated.
- Resolved locale shared once via `AppServiceProvider::boot()` + `View::share`, guest-safe.
- `locale = null` → app default (`en`). Existing users unaffected.

## Invariants every task preserves

- **Event save contract unchanged.** The picker emits one hidden input under the field's
  original name carrying `Y-m-d\TH:i`. Controllers, `UpdateCodexEntryRequest`,
  `WithinEventWindow`, `EventController::datetimeBounds` are not touched.
- **#132 field names.** Inline new-event fields stay `new_inception_event_title` /
  `new_termination_event_title` / `new_event_title`; the existing render guards must stay green.
- **Bookend clamp is a hint; the server is authoritative.** `WithinEventWindow` still rejects
  out-of-window dates.
- **No timezone shift of event dates.** `DateFormat` never `setTimezone`. Real-world dates
  (word-count, challenges) keep their own timezone handling, untouched.
- **No interface-string translation.** Locale affects date/time formatting only.

See `expanded/*.md` for detail; where a doc conflicts with a binding default above, the default wins.
