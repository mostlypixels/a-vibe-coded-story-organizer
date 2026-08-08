# Word Count Goals — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

> [!IMPORTANT]
> An **exception log, not a work journal**. A task that went to plan gets no entry — the
> diff and the task file already record what was built. Bullets under the headings below,
> root cause first, no per-task sections.

## Feedback & decisions

From the grill of 2026-08-08, before decomposition:

- **The chart moved off `projects/show` to its own Progress page** under Tools ▾. PR #89 had
  just filled the dashboard with eight recently-edited tiles, and `word-count-challenges`
  needs somewhere to live that isn't the dashboard.
- **The monthly goal left the feature.** A month with a target is a window with a target,
  which is the definition of a challenge; it moved to
  `.specs/draft/word-count-challenges/`. Project goals are open-ended: a daily *rhythm* and a
  total *destination*.
- **"The first snapshot counts as 0 written" was wrong** and became "before a project's first
  row, its total was 0". The first framing lost a new project's first writing day forever.
  This removed, in order: the migration backfill, a `Project::created` baseline hook, an
  `is_baseline` column, and the import-time baseline row.
- **A baseline row dated *today* collides with same-day writing** — the recorder's upsert
  overwrites it and the hole reopens. Caught during the grill; the reason no baseline row
  survives in the design.
- **Snapshots travel in the export**, reversing the expansion's original answer. An export is
  a backup, and it is also what removed the last need for an import baseline.
- **The daily figure is drawn as bars, not a line.** Each day is its own quantity, and a day
  the writer cut words reads correctly as a bar below the axis.
- **The status strip ignores the range picker.** Fixing it to *now* sidesteps a free 80-day
  period, where a per-month figure has no defined meaning.
- **The Tools landing page is in scope** — it was still the literal word `stub` after #89
  fixed the other three sections.
- **The dashboard gets a real Progress card**, not a link: the dashboard is deliberately
  crowded pre-v1 while every candidate element is tried out.

## Deviations from the spec/plan

_None yet._

## Issues → resolutions

_None yet._
