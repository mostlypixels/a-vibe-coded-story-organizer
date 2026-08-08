# Word Count Goals — plan

The manual for this plan. Never implemented, never moved to `implemented/`.

## Execution order

| # | Task | Purpose |
| --- | --- | --- |
| 01 | `users.timezone` + `WriterDay` | The writer's local day, resolved in one place. Everything downstream dates rows with it. |
| 02 | `word_count_snapshots` table + model | One row per project per day, holding a cumulative total. |
| 03 | `WordCountSnapshotRecorder` + model events | Writes that row on every path that changes the project's words. |
| 04 | `WordCountHistory` + series value objects | Turns rows into a per-day series. Owns all the delta arithmetic. |
| 05 | Project goals — `daily_word_goal`, `total_word_goal` | Two nullable integers on the project form. |
| 06 | Chart.js + `x-word-count-chart` | The dependency, the Alpine module, the component in `full` and `compact` variants. |
| 07 | Progress page shell | Route, controller, nav entry under Tools ▾, status strip. No chart yet. |
| 08 | Progress chart + range picker | The `full` chart and the GET range form on that page. |
| 09 | Dashboard Progress card | Two bars + a 14-day `compact` chart on `projects/show`. |
| 10 | Tools landing page | One card per tool, replacing the `stub`. |
| 11 | Export / import | Goals and snapshots travel in the archive. |
| 12 | Demo history | Generator, seeder trait, artisan command. |
| 13 | Documentation | `word-count-goals.md` deep dive + the two entry points. |

Dependencies: 03 needs 01 + 02 · 04 needs 02 · 06 needs 04's series shape · 07 needs 05 ·
08 needs 06 + 07 · 09 needs 06 · 10 needs 07 · 11 needs 02 + 05 · 12 needs 03.

## Binding decisions

Settled in `expanded/` and by the grill of 2026-08-08. **Do not re-litigate these inside a
task** — if one looks wrong, stop and raise it.

- **Snapshots store the project's cumulative total**, never a delta. Deltas are derived at
  read time and thrown away.
- **Before a project's first row, its total was 0.** No baseline row, no `is_baseline`
  column, no `Project::created` hook, no migration backfill.
- **The day is the project owner's local day**, from `WriterDay`. Never `auth()->user()`,
  never the server's zone.
- **Two goals only: daily and total**, both open-ended and nullable. A goal with a window is
  a challenge (`.specs/draft/word-count-challenges/`) — there is no monthly goal.
- **The chart draws bars for the day's writing and a flat line for the daily goal.** No
  cumulative view in this feature.
- **The status strip always shows *now***, whatever range the chart is on.
- **The chart lives on its own Progress page** under Tools ▾. The dashboard gets a card, not
  a chart with controls.
- **Snapshots and goals travel in the export.** An archive without a snapshots section
  imports as "none", not as an error.
- **Range capped at 366 days**; full `DateTimeZone::listIdentifiers()` for the timezone
  select; snapshots are never pruned.
- **Word counts come from `scenes.word_count` only.** This feature does not touch
  `WordCounter` — see `documentation/word-count.md`.

## Invariants every task must preserve

- **One row per `(project_id, recorded_on)`.** Enforced by a unique index and written with
  `upsert`, never `updateOrCreate` — two autosaves in the same millisecond must not race
  into a constraint violation.
- **The stored figure always equals `SUM(scenes.word_count)` for that project** at the moment
  it was written. Nothing computes a project's words a second way.
- **Bulk writes use `DB::table()` and record nothing.** Inherited from
  `documentation/word-count.md`; the demo generator exists because of it.
- **Authorization walks up to the project** via `ProjectPolicy` — `view` to read, `update` to
  set goals — and is mirrored in every Form Request's `authorize()`. Every new endpoint gets
  a non-owner 403 test.
- **Controllers aggregate, views render.** No `wordCount()`-style accessor that a Blade loop
  can call; `projects/show` is already the heaviest page in the app.
- **Ancestor word totals stay a `SUM`.** Do not add a `word_count` column to `projects`,
  `acts` or `chapters` — benchmarked and rejected in `documentation/word-count.md`.
