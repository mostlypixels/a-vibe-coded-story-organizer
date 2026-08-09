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

- **`Scene::saved`'s guard is wider than the planned `wasChanged('word_count')`.** Eloquent
  calls `syncChanges()` in `performUpdate()` only, so `wasChanged()` is always false at
  `saved` for a row that was just inserted. A scene created with text — the story form posts
  `contents`, and the importer creates through `$chapter->scenes()->create()` — would have
  moved the total with no row written. The hook now reads
  `$scene->wasRecentlyCreated ? $scene->word_count > 0 : $scene->wasChanged('word_count')`:
  a new empty scene still records nothing.

- **The chart's Alpine component takes an injectable `createChart`.** jsdom gives a `<canvas>`
  no 2D context, and Chart.js only logs when it cannot acquire one — a real instance in the
  vitest suite would prove nothing about mount and teardown. The default argument is the real
  `new Chart(...)`, so the browser path is unchanged.

- **The dashboard card resolves the writer's day from `auth()->user()`, not
  `$project->user`.** `WriterDay`'s docblock warns against `auth()->user()` in general, but
  `ProjectController::show()` runs after `authorize('view', $project)`, which already proves
  the two are the same row. Using `$project->user` there costs a third query (a lazy-loaded
  `users` row) on top of `WordCountHistory::series()`'s two, breaking the task's "two extra
  queries, not one per day" budget. The general rule still holds anywhere the acting user
  isn't already proven to be the resource owner.

## Issues → resolutions

- **`recorded_on` was stored in two formats.** A `date` cast makes Eloquent write
  `'2026-03-01 00:00:00'`, while the recorder's `upsert` writes the bare `'2026-03-01'` from
  `WriterDay::dateFor()`. Two effects, neither loud: a `whereBetween` on `'Y-m-d'` dropped the
  range's last day, and an Eloquent-written row missed the `(project_id, recorded_on)` unique
  key, so the same day could get two rows. `WordCountSnapshot` now has a `recordedOn`
  `Attribute` instead of the cast — it sets `toDateString()` and gets a `CarbonImmutable` —
  and `WordCountSnapshotTest` asserts the recorder updates a factory-made row for the same
  day.
- **A running Vite dev server can hold a `node_modules` the host never touched.** `public/hot`
  pointed at a containerised dev server, so a host `npm i chart.js` left the browser resolving
  `chart.js` to a 500: Alpine never started and the page looked broken for a reason that was
  not the code. Verify the chart against the **build** — move `public/hot` aside, drive the
  page, then restore it.
- **`WordCountSeries::isEmpty()` cannot detect "this project has no history."** The series
  fills every day of the requested range with a zero-total entry, so it is never empty for a
  valid range — only `to < from` produces an empty collection, which validation already
  rejects. The Progress page's empty state now checks
  `WordCountSnapshot::where('project_id', ...)->exists()` directly in the controller instead.
