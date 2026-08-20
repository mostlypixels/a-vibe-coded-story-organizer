# Word count challenges — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

> [!IMPORTANT]
> An **exception log, not a work journal**. A task that went to plan gets no entry — the
> diff and the task file already record what was built. Bullets under the headings below,
> root cause first, no per-task sections.

## Feedback & decisions

- **Par counts finished days only**, so day 1 opens at par 0. Grill, 2026-08-20. The expanded
  design had par include today, which scolds the writer before breakfast every day and
  contradicts the shipped streak rule ("today extends a streak but never breaks one").
  `expanded/architecture.md` was corrected.
- **A recurring challenge may carry an optional `ends_on`.** Grill, 2026-08-20. Delete was
  otherwise the only way to stop one, which would erase the record the spec asks to keep.
  `expanded/data-model.md` was corrected.
- Monthly recurrence ships in the same pass; a monthly challenge's first month is not clipped
  to `starts_on`; fixed windows cap at 366 days; negatives and overshoot are both shown as
  they are; edits are silent; no dashboard change; no index route. All confirmed in the grill.
- **`ImportRules::SUPPORTED_MANIFEST_VERSIONS` became `[4, 5]`**, not a `[4]` → `[5]`
  replacement. Version 4 archives carry no incompatible layout change — only a new optional
  file — so they still import cleanly, matching the task's own "a version-4 archive with no
  challenges file imports cleanly" test. A `[3]` → `[4]` bump was a real layout break and
  dropped `3`; this bump is additive, like the earlier `[1]` → `[1, 2]` step.
- The chart is a **second component in the same JS module**, not a third variant of
  `x-word-count-chart` — the source spec's "reuse the chart" means the module, not the config.

## Deviations from the spec/plan

- **`ChallengeStanding` carries a `days` field** the architecture field table does not list.
  The chart's bar dataset is `written` per day, and deriving it from `dailyTotals`
  differences would put arithmetic back in the view — the one rule the standing exists to
  keep. `dailyTotals` and `parTotals` are unchanged.
- **`elapsedDays` is computed by `ChallengeWindow::elapsedDays($today)`**, not inside
  `ChallengeProgress`. The clamp needs the same UTC calendar-date anchoring the window
  already owns privately; duplicating it in the service is how a DST off-by-one gets in.

## Issues → resolutions

- **`WordCountFormat::text()` returned the singular branch for a negative count** — "-2,300
  word". None of the translation key's ranges (`{0}`, `{1}`, `[2,*]`) matches a negative
  number, so `trans_choice()` fell through. Harmless until this feature, which is the first
  caller that can pass one: a challenge whose writer cut more than they added. The plural
  branch is now chosen on `abs($count)`, with a case in `WordCountComponentTest`.
- **The ahead/behind headline read "Ahead by 23"** — a number with no unit, found in the
  browser pass, not by the suite. It now uses `x-word-count` like every other figure on the
  page: "Ahead by 23 words".
