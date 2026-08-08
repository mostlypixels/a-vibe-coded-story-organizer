# 06 — Chart.js and the chart component

## Scope

- `npm i chart.js` — a runtime dependency, the project's first charting library.
- `resources/js/word-count-chart.js` exporting `registerWordCountChart(Alpine)`, registered
  in `resources/js/app.js` beside `registerWordCount(Alpine)`.
- `resources/views/components/word-count-chart.blade.php` — `:series`, `:dailyGoal`,
  `variant` (`full` | `compact`), owning the `x-data` and the `<canvas>`.
- `resources/js/word-count-chart.test.js` (vitest, co-located).

**Not** in this task: any page that uses it. 08 and 09 mount it.

## Depends on

04 (the series shape it draws).

## Key decisions

- **Import the pieces, not `chart.js/auto`** — `BarController`, `BarElement`,
  `LineController`, `LineElement`, `PointElement`, `LinearScale`, `CategoryScale`, `Tooltip`.
  `auto` pulls pie, radar, polar, scatter and bubble into `app.js` for one chart.
- **Bars for the day's writing, a flat line for the daily goal**, mixed types on one axis.
  Each day is its own quantity; a line would imply the value flows between days, and a cut
  day would read as a continuous quantity going negative. Rest days draw no bar.
- **The goal line only exists when `daily_word_goal` is set** — one dataset, not a dataset of
  `null`s.
- **`chart.destroy()` in the Alpine component's `destroy()`.** Chart.js keys its registry by
  canvas and throws *"Canvas is already in use"* on re-mount.
- **Theme colours are read at mount** with `getComputedStyle(canvas)` against the CSS custom
  properties from `components/theme-style.blade.php`. Never hard-code a hex.
- **No data fetching.** The series arrives server-rendered via `@js($series)`.
- One component with two variants, not two components — the compact form is the same data
  drawn smaller, and a copy would drift.

## Consult

`expanded/ui.md` → *Chart.js*, *Bars for the day, a line for the goal*, *The chart component*

## Tests

- Mounting twice over the same canvas destroys the first instance.
- A `null` daily goal yields a one-dataset config.
- `variant="compact"` omits axes and tooltips.
- A Blade component test that the component renders its canvas and serialises the series.
