# 06 — The challenge chart

## Scope

- `challengeChartConfig()` and an Alpine `challengeChart` component added to
  `resources/js/word-count-chart.js`, registered in `resources/js/app.js`.
- `resources/views/components/challenge-chart.blade.php`.
- Wire it into the running `x-challenge-card`.

**Not** in this task: any change to `x-word-count-chart`, its config builder or its callers.

## Depends on

03, 05.

## Key decisions

- Three datasets on two axes — exact table in `expanded/ui.md` → *The chart*.
- The climbing line is `null` after today; do not pad it to the deadline.
- `y1` sits on the right, `beginAtZero`, `max: target`, so the line touching the top edge is
  the challenge being met.
- Colours come from `themeColors()`; no hex anywhere. Negative bars use the danger token, as
  the shipped chart already does.
- Reuse the module's existing Chart.js registrations — import nothing new.
- Destroy the chart in the Alpine component's `destroy()`, same as `wordCountChart`.
- Upcoming and past challenges get no canvas.

## Consult

`expanded/ui.md` → *The chart*.

## Tests

`resources/js/challenge-chart.test.js`, following `word-count-chart.test.js` — config builder
only, no DOM chart:

- three datasets, with the right types and axes;
- the climbing line is `null` for every day after today;
- `y1.max` equals the target;
- negative bars take the danger colour;
- a window with no snapshot rows still produces a valid config.

Add a Blade render case to `tests/Feature/WordCountChartComponentTest.php` (or a sibling) so
the new component compiles with a real standing.
