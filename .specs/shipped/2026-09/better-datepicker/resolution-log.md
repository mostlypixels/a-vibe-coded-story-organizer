# Better datepicker — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

> [!IMPORTANT]
> An **exception log, not a work journal**. A task that went to plan gets no entry — the
> diff and the task file already record what was built. Bullets under the headings below,
> root cause first, no per-task sections.

## Feedback & decisions

- Locale scope widened from `en/fr/it` to the **24 official EU languages + `en-US`**. Because
  the set is large, the design switched from a hand `clock`/`order` map to **deriving** from
  `ext-intl`/Carbon: display via `isoFormat` tokens, picker order/clock via the locale's ICU
  pattern. `ext-intl` is required and present.
- Consequently `LocaleChoice` is **thinner** than `expanded/architecture.md` first stated — slug,
  name, Carbon locale code only; no `clock`/`order` fields. The expanded doc predates the grill;
  `plan/00-overview.md` binding defaults win.
- Event dates are **never timezone-shifted** — they are in-universe instants. `DateFormat` must
  not `setTimezone`.
- Event-date **display** migrates app-wide in this feature; real-world date displays (word-count,
  challenge, progress) stay as-is.

## Deviations from the spec/plan

- The ICU derivation helpers (`monthNames`, `segmentOrder`, `usesTwelveHourClock`) live on
  `DateFormat`, not on `LocaleChoice`. `LocaleChoice` stays a thin config record; all
  locale-pattern knowledge sits in one class.
- A picker root carries `data-date-field="{name}"`. Tests that used to assert
  `type="datetime-local"` assert that hook instead.
- Task 05 swapped `<x-date-field>` into only `single-event-field.blade.php` and
  `scenes/create.blade.php`. The other three named sites were skipped:
  `challenges/partials/fields.blade.php`'s `starts_on`/`ends_on` are plain `type="date"`
  calendar dates, not `Y-m-d\TH:i` — `dateField.parseValue()` only matches the datetime
  pattern, so the picker would show existing values as blank. `revision-picker.blade.php`
  and `progress/index.blade.php`'s date inputs are search/report filters, not entry fields,
  per the task's own "filter use needs no picker" rule.

## Issues → resolutions

- The picker clamped a date outside the window **silently**, so a bad year saved as a valid
  date with no word to the writer. The clamp now shows a note under the field naming the
  instant it moved the date to.
- The clamp landed a regular event **on** the bookend instant. `WithinEventWindow` allows that
  (the window is inclusive), but an event that shares the End instant sorts after End in the
  event list. The clamp now stops one minute inside the window.
- A new project's End bookend was `3000-01-01 00:00`, which put the whole of its own day out
  of the window. End is now `3000-01-01 23:59`.
