# 07 — The Progress page shell

Route, nav and status strip. The chart arrives in 08, so this task ends with a working page
that shows where the writer stands and nothing more.

## Scope

- Route `GET /projects/{project}/progress` → `ProgressController@index`, name
  `projects.progress`, inside the `auth` group beside `projects.revisions.index`.
- `App\Http\Controllers\ProgressController`.
- Nav entry under **Tools ▾**, after Revisions — both `navigation/project-menu.blade.php` and
  `navigation/responsive-project-menu.blade.php`, plus whatever drives `toolsActive`.
- `resources/views/progress/index.blade.php` with the **status strip**: today's words against
  the daily goal, and the current total against the total goal, each with a progress bar.
- Breadcrumbs, matching what #88 established.

**Not** in this task: the chart and the range picker (08).

## Depends on

05 (the goals it displays). Uses 04's service for today's figure.

## Key decisions

- **The status strip always shows *now***, and will not move when 08 adds the range picker.
  The page then splits into a strip answering *how am I doing today* and a chart answering
  *what did I do back then* — which also sidesteps a free 80-day period, where a per-month
  figure has no defined meaning.
- **A `null` goal drops its row entirely** — no bar, no "of ∞", no divide by zero.
- Authorization is `ProjectPolicy@view`, mirrored in the Form Request that 08 introduces.
  This task can authorize in the controller and hand over.
- Every number goes through `x-word-count` so the page cannot disagree with the dashboard
  about `1,234` vs `1234`.

## Consult

`expanded/ui.md` → *The Progress page* · `expanded/architecture.md` → *Routes and controllers*

## Tests

- Owner → 200 and both figures render. Non-owner → 403.
- A project with no goals renders the strip without goal rows.
- The nav shows Progress under Tools, marked active on the page.
