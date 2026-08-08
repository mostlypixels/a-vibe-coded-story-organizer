# 08 — The Progress chart and range picker

## Scope

- `App\Http\Requests\ShowProgressRequest` — a GET Form Request (precedent: `SearchRequest`).
- Range resolution in `ProgressController@index`, handed to `WordCountHistory` as two dates.
- The `full` chart mounted on the Progress page.
- The range picker: a plain GET `<form>` with a month `<select>` (last 12 months) and two
  `x-text-input type="date"` for a free period, plus a real submit button.
- The empty state when a project has no snapshots.

## Depends on

06 (the component), 07 (the page).

## Key decisions

- **Rules:** `from`/`to` `nullable|date`, `to` `after_or_equal:from`, and a **366-day span
  cap** — the series materialises one entry per day in PHP, so a five-year request is a
  memory question.
- **`authorize()` mirrors `$this->user()->can('view', $this->route('project'))`.** A non-owner
  must 403 from the request, not only the controller.
- **Default range: the current month in the *owner's* timezone** —
  `WriterDay::for($project->user)->startOfMonth()`, not `now()->startOfMonth()`.
- **Range resolution lives in the controller**, per CLAUDE.md's index-filtering convention,
  not in a query scope.
- **Full page reload, range in the URL.** Rejected: a JSON endpoint the chart fetches — a
  second authorized surface and a second serialization of the same data.
- **The month select shows all 12 months** even where the project has no history; an empty
  month renders the empty state, which is a true answer. A list that changes shape as you use
  the app is worse.
- **Empty state, not an empty chart.** A flat line at zero claims "you wrote nothing", which
  is a different and wrong statement from "nothing is recorded".
- The picker is a real form with a real submit button, so it works before Alpine boots.

## Consult

`expanded/ui.md` → *The Progress page* · `expanded/architecture.md` → *Routes and controllers*

## Tests

`expanded/testing.md` → *`ProgressPageTest`*, all of it. Specifically the default range under
`travelTo()` with a non-UTC owner, `from` after `to`, and a span over 366 days.
