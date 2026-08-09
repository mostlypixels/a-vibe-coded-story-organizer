# UI

Two surfaces: a **Progress page** under Tools that owns the chart and the range picker, and
a **dashboard card** that shows where you stand without asking you to navigate.

## Chart.js

The first charting dependency in the project — `npm i chart.js` (runtime dependency, beside
`@tiptap/*`).

Import the pieces, not `chart.js/auto`:

```js
import { Chart, BarController, BarElement, LineController, LineElement, PointElement, LinearScale, CategoryScale, Tooltip } from 'chart.js';
Chart.register(BarController, BarElement, LineController, LineElement, PointElement, LinearScale, CategoryScale, Tooltip);
```

`chart.js/auto` pulls every controller (pie, radar, polar, scatter, bubble) into the single
`app.js` bundle for one mixed bar/line chart.

`resources/js/word-count-chart.js` exports `registerWordCountChart(Alpine)`, registered in
`resources/js/app.js` beside `registerWordCount(Alpine)`. Follow `revision-picker.js`'s
shape for a component that owns a DOM node.

- **Destroy on teardown.** Chart.js keeps a registry keyed by canvas; re-initialising over a
  live instance throws *"Canvas is already in use"*. Call `chart.destroy()` in the Alpine
  component's `destroy()`.
- **No data fetching.** The series arrives server-rendered via `@js($series)`; the module
  only draws.

### Bars for the day, a line for the goal

Words written per day is a **bar** dataset. A line implies the quantity flows between its
points — that a day between 800 and 1,200 was around 1,000. It wasn't; each day is its own
number. A day the writer cut 300 words is a bar below the axis, which reads correctly,
where a line dipping under zero reads as a continuous quantity going negative.

The daily goal is a flat **line** dataset over the bars, drawn only when `daily_word_goal`
is set. Chart.js mixes types natively — `type` per dataset, one shared axis.

Rest days render as no bar at all, which is more honest than a line touching zero.

### Theme colours

The app themes through CSS custom properties (`components/theme-style.blade.php`), and
Chart.js needs literal colour strings. Read them once at mount with
`getComputedStyle(canvas)` and pass them into the config — never hard-code a hex, or the
chart is the one element that ignores the user's theme.

## The chart component

`resources/views/components/word-count-chart.blade.php`, taking `:series`, `:dailyGoal` and
a `variant`. Blade stays free of chart config.

| `variant` | Used by | Shows |
| --- | --- | --- |
| `full` (default) | Progress page | axes, tick labels, tooltips, point labels |
| `compact` | dashboard card | bars and the goal line only — no axes, no labels, no tooltip |

One component, two variants — not two components. The compact form is the same data drawn
smaller, and a second implementation would drift.

## The Progress page

`resources/views/progress/index.blade.php`, reached from **Tools ▾ → Progress**.

Top to bottom:

1. **Status strip** — two figures, fixed to *now* regardless of the chart's range:
   - **Today** — words written today against the daily goal, with a progress bar.
   - **Total** — the project's current total against the total goal, with a progress bar.
2. **Range picker** — a plain GET `<form>`:
   - a month `<select>` (the last 12 months) that fills `from`/`to` with that month's bounds;
   - two `x-text-input type="date"` for a free period;
   - a real submit button, so the picker works before Alpine boots.
3. **The chart**, `variant="full"`.

The readouts do not follow the range. The page then splits into two honest halves — a strip
answering *how am I doing today*, and a chart answering *what did I do back then*. It also
sidesteps a free period of 80 days, where a per-month figure has no defined meaning.

A goal that is `null` drops its row entirely — no bar, no "of ∞".

### Empty state

A project with no snapshots renders `x-table-empty`-style copy in place of the canvas:
*"No writing recorded yet. Save a scene and today's words appear here."* Do **not** draw an
empty chart — a flat line at zero reads as "you wrote nothing", which is a different and
wrong claim.

## The dashboard card

`resources/views/projects/show.blade.php`, an `x-card` below the word-count header.

```
Progress                                    View history →
─────────────────────────────────────────────────────────
Today        340 / 1,000 words   ▓▓▓▓░░░░░░░░
Total     50,500 / 90,000 words  ▓▓▓▓▓▓▓░░░░░

▁▃▂▅▁░▄▆▃▂▅▄▁▃      ← last 14 days, compact variant
```

**Last 14 rolling days, not the current month.** On the 1st, a current-month chart is a
single bar — the card looks broken on the day it should be most encouraging. Fourteen
rolling days always reads as a chart, and it answers the dashboard's question: am I in a
groove, or have I stalled.

"View history →" goes to the Progress page.

The dashboard is deliberately crowded right now — every candidate element is being tried
before a pre-v1 clean-up — so this card does not have to fight for its place.

## Goals form

`resources/views/projects/edit.blade.php`, in the existing project-fields form — two
`x-text-input type="number" min="0"` with `x-input-label` / `x-input-error`, grouped under a
sub-heading. No new route; `ProjectController::update` already handles the form.

Placeholder text carries the "leave empty for no goal" affordance rather than a checkbox.

## Tools landing page

`resources/views/tools/home.blade.php` — currently the literal word `stub`, while #89 gave
Story, Timeline and Codex real landing pages.

**One card per tool**, in the same grid the other three use: title, one sentence saying what
it is for, and a link. Two cards today — **Revisions** and **Progress**.

Not an `x-recent-list`: Tools has no entities to list. `RecentlyEdited`'s own docblock rules
revisions out (immutable, `created_at` only), and "recently created revisions" is a strange
thing to land on. No live numbers on the Progress card either — the dashboard and the
Progress page already show that figure, and a third copy is one too many.

## Timezone selector

`resources/views/profile/partials/update-profile-information-form.blade.php`, after Email:
an `x-select` over `DateTimeZone::listIdentifiers()`, grouped by region, with a first option
*"Use the site default"* (value `""` → stored `null`).

The list is ~420 entries. A plain grouped `<select>` is fine and needs no JS; do not reach
for a searchable combobox the project does not have.
