# UI

Everything lands on the existing **Tools ▸ Progress** page. No new nav item, no new section.

## Progress page layout

`resources/views/progress/index.blade.php`, after the status strip and before the range
picker card — a challenge is *now*, the chart below is *back then*.

1. **Running** — one `x-challenge-card` each, ordered by nearest deadline.
2. **Upcoming** — the same card without a chart, showing window and target only.
3. **Past** — a plain `x-table`: name, window, written / target, a met/missed `x-badge`.
   Capped at 12 rows, newest first. No charts; a finished challenge is a record, and twelve
   canvases would make the page about history.
4. A **New challenge** `x-button` in the section header, and `x-icon-edit-link` /
   `x-icon-delete-button` on each card, matching `plotlines/index.blade.php`.

With no challenges at all, the section is a single line of copy plus the button, not an
empty table.

## `components/challenge-card.blade.php`

Takes one `ChallengeStanding` and the `Challenge`. It reads, not calculates.

```
Fifty in November                 1–30 Nov · monthly        ✎ 🗑
─────────────────────────────────────────────────────────────────
31,200 / 50,000 words   ▓▓▓▓▓▓░░░░       ahead by 1,533
Par today 29,667 · 6 days left · 3,134 words a day to finish

▁▃▂▅▁░▄▆▃▂▅▄▁▃  ← bars, climbing line, par line
```

- The progress bar is the shipped `x-progress-bar` (`:value="$standing->written"`,
  `:goal="$standing->target"`).
- **Ahead/behind is the headline number**, in the accent colour when `delta >= 0` and the
  danger colour when negative — it is the one thing a standing daily goal could never say.
- `perDayNeeded` shows only while running and only when `remaining > 0`; a challenge already
  past its target says *target reached* instead of "0 words a day".
- A finished card drops the par/days line and shows the verdict badge.

## The chart

A **new** Blade component `components/challenge-chart.blade.php` and a **new** Alpine
component `challengeChart`, added to the existing `resources/js/word-count-chart.js` module
so it shares `themeColors()` and the Chart.js registration.

Three datasets on two axes:

| Dataset | Type | Axis | Data |
| --- | --- | --- | --- |
| words that day | bar | `y` | `written` per day, danger colour when negative |
| words so far | line | `y1` | `dailyTotals`, **`null` after today** |
| par | line | `y1` | `parTotals`, dashed, whole window |

Two axes because the deltas (hundreds) and the climbing total (tens of thousands) share no
scale; one axis flattens the bars into the floor. `y1` is `position: 'right'`, `beginAtZero`,
`max: target`, so the climbing line meeting the top edge *is* the challenge being met.

The climbing line ends at today. Padding it to the window's end would draw a flat line to the
deadline, which reads as a prediction the app is not making.

`LineController`, `PointElement` and `LinearScale` are already registered by the module;
nothing new is imported from Chart.js.

**Rejected: a third `variant` on `x-word-count-chart`.** Its props are `series` and
`dailyGoal`, its axis model is single, and every existing caller would start carrying a
`target` it ignores. The two components share the module, not the config.

## Create / edit form

`resources/views/challenges/create.blade.php` and `edit.blade.php`, mirroring
`plotlines/create.blade.php`.

Fields: `x-text-input` name; `x-select` recurrence (*One-off* / *Every month*);
`x-text-input type="date"` start and end; `x-text-input type="number" min="1"` target.

- The end date row is hidden by Alpine when recurrence is *Every month*, with a line of help
  text: *"Runs every calendar month until you delete it."* Server-side,
  `required_if:recurrence,none` is the real rule — the hide is convenience, not validation.
- A live **par** hint under the target — *"about 1,667 words a day"* — recomputed by Alpine
  from the three fields. It makes an unreachable target obvious before saving, and it is the
  only place par is ever shown before a challenge exists.
- `x-input-error` on every field; delete lives on the card, not in the form.
