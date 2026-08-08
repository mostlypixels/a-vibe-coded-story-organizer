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
- Two editable goals on the project: a **daily** goal (a rhythm) and a **total** goal (a
  destination). Both are open-ended — neither has a window.
- A **Progress** page under Tools: words written per day as bars, the daily goal as a flat
  line over them, current month by default, with a range picker.
- A Progress card on the project dashboard: two progress bars and a compact 14-day chart.

## Non-goals

- **Anything with a window and a target** — that is a challenge, and it lives in
  `.specs/draft/word-count-challenges/`. This is why there is no monthly goal here: "20,000
  this month" and "50,000 in November" are the same object, and it is not a project goal.
- Changing `WordCounter` or what gets counted. Snapshots consume `scenes.word_count`.
- History below project level (no per-act, per-chapter, per-scene series).
- A cumulative "climbing toward the target" chart. That is what a challenge draws.
- Streaks, reminders, notifications.

## User stories

- As a writer I open Progress and see how much I wrote each day this month.
- I set a daily goal of 1,000 words and see which bars clear the grey line.
- I set a total goal of 90,000 and see 45,000 / 90,000 on my dashboard.
- I live in Auckland; a session that ends at 1 a.m. counts as that day's writing, not the
  previous UTC day's.
- I delete a scene and the day's bar goes below zero.
- I restore an export and my history comes back with it.

## Acceptance criteria

| | |
|---|---|
| A scene save creates or updates today's row for its project | one row per `(project_id, recorded_on)`, ever |
| The stored figure equals `SUM(scenes.word_count)` for the project at that moment | same number as the project header |
| The day is resolved in the **project owner's** timezone | not the actor's, not the server's |
| A day with no save has no row | and reads as 0 words written, not as a gap |
| Before a project's first row, its total was **0** | so a new project's first writing day is counted in full |
| A non-owner gets 403 on every new route | `ProjectPolicy`, as usual |
| Goals are nullable | `null` = "no goal", which is not the same as 0 |
| Changing a goal redraws past days | goals are not historicized |
| An export carries goals **and** snapshots | restoring a backup restores the history |

## Conflicts with existing invariants

- **Bulk writes bypass the recorder.** `documentation/word-count.md` requires seeders,
  migrations and backfills to use `DB::table()`, so no model event fires. Snapshots inherit
  that: a seeded project records nothing as a side effect of being seeded, which is why
  [demo-history](demo-history.md) exists.
- **Cascading deletes bypass it too.** Deleting an act drops its chapters and scenes at the
  database level, firing no `Scene::deleted`. Handled explicitly in
  [architecture](architecture.md) → *The write path*.
- **`projects/show` is the heaviest page in the app**, and PR #89 added eight recently-edited
  queries to it. The dashboard card adds two more; the full chart lives on its own page
  partly for this reason.
