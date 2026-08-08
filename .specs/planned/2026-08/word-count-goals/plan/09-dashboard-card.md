# 09 — The dashboard Progress card

## Scope

- `ProjectController::show()` gains the card's data: a 14-day series and the two goals.
- An `x-card` on `projects/show.blade.php`, below the word-count header: two progress bars
  (Today, Total) and a `compact` chart, with a "View history →" link to `projects.progress`.

## Depends on

06 (the component). Reads 04's service and 05's goals.

## Key decisions

- **Last 14 rolling days, not the current month.** On the 1st a current-month chart is a
  single bar — the card looks broken on the day it should be most encouraging. Fourteen days
  always reads as a chart and answers the dashboard's question: in a groove, or stalled?
- **The `compact` variant** — no axes, no tick labels, no tooltip, no range picker. Those
  live on the Progress page.
- **A `null` goal drops its row.** No "of ∞".
- **Two extra queries, not one per day.** `projects/show` is the heaviest page in the app and
  #89 already added eight; assert the budget.
- The dashboard is deliberately crowded pre-v1 (every candidate element is being tried before
  a clean-up), so this card does not need to displace anything.

## Consult

`expanded/ui.md` → *The dashboard card*

## Tests

- The card renders with a 14-day series.
- A project with no goals renders it without goal rows.
- Query-budget assertion: exactly two more queries than before.
- Non-owner still 403s on `projects.show`.
