# UI

## Chart.js

The first charting dependency in the project — `npm i chart.js` (runtime dependency, beside
`@tiptap/*`).

Import the pieces, not `chart.js/auto`:

```js
import { Chart, LineController, LineElement, PointElement, LinearScale, CategoryScale, Tooltip, Filler } from 'chart.js';
Chart.register(LineController, LineElement, PointElement, LinearScale, CategoryScale, Tooltip, Filler);
```

`chart.js/auto` pulls every controller (bar, pie, radar, polar, scatter, bubble) into the
single `app.js` bundle for one line chart.

`resources/js/word-count-chart.js` exports `registerWordCountChart(Alpine)`, registered in
`resources/js/app.js` beside `registerWordCount(Alpine)`. Follow `revision-picker.js`'s
shape for a component that owns a DOM node.

- **Destroy on teardown.** Chart.js keeps a registry keyed by canvas; re-initialising over a
  live instance throws *"Canvas is already in use"*. Call `chart.destroy()` in the Alpine
  component's `destroy()`.
- **No data fetching.** The series arrives server-rendered via `@js($series)`; the module
  only draws.

### Theme colours

The app themes through CSS custom properties (`components/theme-style.blade.php`), and
Chart.js needs literal colour strings. Read them once at mount with
`getComputedStyle(canvas)` and pass them into the config — never hard-code a hex, or the
chart is the one element that ignores the user's theme.

## The dashboard card

`resources/views/projects/show.blade.php` gains one `<x-card>` below the word-count header,
above the recently-edited tiles.

| Part | Component |
|---|---|
| Card + heading | `x-card` with a `header` slot, `x-heading level="3"` |
| Range picker | `x-select` (month list) + two `x-text-input type="date"` + `x-button` |
| Chart | a bare `<canvas>` inside the Alpine component |
| Progress readouts | `x-word-count` for every number rendered |

New component: `resources/views/components/word-count-chart.blade.php`, taking `:series`
and `:dailyGoal` and owning the `x-data` + `<canvas>`. Blade stays free of chart config.

### Lines

- **Coloured line — words written per day** (`written`), not the cumulative total. Both
  lines have to share one Y axis, and a flat daily-goal line only means anything against a
  per-day figure. Confirmed in [open-questions](open-questions.md).
- **Grey line — the daily goal**, flat, rendered only when `daily_word_goal` is set.
- Point labels show the day's figure, per the source spec. On a 31-point month these
  collide; enable Chart.js `autoSkip` on the X ticks and let the tooltip carry the exact
  number.

### Range picker

Both spec modes (`by month`, `by period`) post the same two dates:

- A month `<select>` (last 12 months, labelled in the writer's locale) that fills `from`/`to`
  with that month's bounds.
- Two date inputs for a free period.
- Submits as a GET form to `projects.show`, so the range lives in the URL and is
  shareable and back-button-correct. Full page reload — see
  [architecture](architecture.md) → *Rejected*.

Keyboard: a real `<form>` with a real submit button. No Alpine-only interaction on the
range, so the picker works before JS boots.

### Progress readouts

The daily goal is the chart's grey line. The other two have no line and go under it as
plain text, so all three goals have somewhere to live:

- **This month** — `written this month / monthly_word_goal`
- **Total** — `current total / total_word_goal`

Each is hidden when its goal is `null`. Every number goes through `x-word-count` so the card
cannot disagree with the header about `1,234` vs `1234`.

### Empty state

A project with no snapshots (every existing project, and every seeded one) renders
`x-table-empty`-style copy in place of the canvas: *"No writing recorded yet. Save a scene
and today's words appear here."* Do **not** draw an empty chart — a flat line at zero reads
as "you wrote nothing", which is a different and wrong claim.

## Goals form

`resources/views/projects/edit.blade.php`, in the existing project-fields form — three
`x-text-input type="number" min="0"` with `x-input-label` / `x-input-error`, grouped under
a sub-heading. No new route; `ProjectController::update` already handles the form.

Placeholder text carries the "leave empty for no goal" affordance rather than a checkbox.

## Timezone selector

`resources/views/profile/partials/update-profile-information-form.blade.php`, after Email:
an `x-select` over `DateTimeZone::listIdentifiers()`, grouped by region, with a first option
*"Use the site default"* (value `""` → stored `null`).

The list is ~420 entries. A plain grouped `<select>` is fine and needs no JS; do not reach
for a searchable combobox the project does not have.
