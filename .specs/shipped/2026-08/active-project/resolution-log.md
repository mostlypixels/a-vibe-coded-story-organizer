# Active project persistence — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

> [!IMPORTANT]
> An **exception log, not a work journal**. A task that went to plan gets no entry — the
> diff and the task file already record what was built. Bullets under the headings below,
> root cause first, no per-task sections.

## Feedback & decisions

- **Tracking happens on every successful project page load, not in `ProjectController::show`.**
  The `show()`-only draft could not activate a project reached by bookmark, so the nav silently
  reverted to the previous project on the next page with no project in its URL. Middleware writing
  *after* the response, gated on 2xx, is post-authorization by construction — which is what let the
  ownership check be dropped entirely. (Grill, 2026-08-03; `expanded/open-questions.md` → Q3.)
- **The project menu over `/admin/*` and `/profile` is the point of the feature**, not a
  consequence of it: a settings detour must cost one click to return from. (Q6.)
- **A bare login lands on the active project**, reversing the expanded docs' recommendation — the
  same "don't route the user through a page they didn't ask for" argument as Q6. (Q4.)
- **The `<title>` stays tied to the URL.** Titles carry project + app name and never the page
  name, so the dashboard tab is the only authenticated tab distinguishable in a row of them.
  Letting it follow the stored project erases that. (Q1.)

## Deviations from the spec/plan

- **`documentation/architecture.md` gained a fourth bullet beyond the three sections task 04
  scoped** — the write rule (`TrackActiveProject`, the 2xx gate, "a page with no project never
  clears"). Root cause: no task in the plan assigned documentation for 01–03, so the middleware
  would have shipped undocumented. Folded into *Navigation active state* rather than a new
  `documentation/active-project.md`, which the feature is too small to earn.

## Issues → resolutions

_None yet._
