# Overview

## Problem

`documentation/word-count.md` ships one number per scene and sums it upward. That answers
*how long is this project?* and nothing else. There is no time axis, so the app cannot say
whether the writer wrote today, whether they are keeping up, or whether they are slowing
down.

## Goals

- One row per project per day holding the project's **total** word count on that date.
- Rows written by the act of saving; no scheduler, no queue.
- The date is the **writer's** local date, from a new `users.timezone`.
- Three editable goals on the project: daily, monthly, total.
- A line chart on the project dashboard: words per day against the daily goal, current
  month by default, with a range picker.
- Monthly and total goals surface as progress readouts beside the chart, not as chart lines.

## Non-goals

- **Challenges** — a named window with a target and a par line. `.specs/draft/word-count-challenges/`.
- Changing `WordCounter` or what gets counted. Snapshots consume `scenes.word_count`.
- History below project level (no per-act, per-chapter, per-scene series).
- Backfill. History starts the day the migration runs.
- Streaks, reminders, notifications, exports of the series.

## User stories

- As a writer I open my project and see how much I wrote each day this month.
- I set a daily goal of 1,000 words and see whether each bar clears the grey line.
- I set a total goal of 90,000 and see 45,000 / 90,000 on the dashboard.
- I live in Auckland; a session that ends at 1 a.m. counts as that day's writing, not the
  previous UTC day's.
- I delete a scene and the day's figure goes down.

## Acceptance criteria

| | |
|---|---|
| A scene save creates or updates today's row for its project | one row per `(project_id, recorded_on)`, ever |
| The stored figure equals `SUM(scenes.word_count)` for the project at that moment | same number as the project header |
| The day is resolved in the **project owner's** timezone | not the actor's, not the server's |
| A day with no save has no row | and reads as 0 words written, not as a gap |
| The first-ever snapshot's daily figure is 0 | the words predate tracking; see [open-questions](open-questions.md) |
| A non-owner gets 403 on every new route | `ProjectPolicy`, as usual |
| Goals are nullable | `null` = "no goal", which is not the same as 0 |
| Changing a goal redraws past days | goals are not historicized — stated in the spec |

## Conflicts with existing invariants

- **Bulk writes bypass the recorder.** `documentation/word-count.md` requires seeders,
  migrations and backfills to use `DB::table()`, so no model event fires. Snapshots
  inherit that: seeded projects get no history. This is wanted ("no history before you
  turn it on"), but it means `MelusineSeeder*` projects open on an empty chart. See
  [open-questions](open-questions.md).
- **Cascading deletes bypass it too.** Deleting an act drops its chapters and scenes at the
  database level, firing no `Scene::deleted`. Handled explicitly in
  [architecture](architecture.md) → *The write path*.
