---
status: expanded
expanded: 2026-08-08
---

# Word Count Goals

The shipped `word-count` feature answers *how long is this project?* It cannot answer *am I
writing?* — there is no history, so no progress and no goals.

## What is stored

One row per project per day: **the project's total word count on that date** — a snapshot,
not the day's delta.

- Snapshot, because every daily figure derives from it by subtraction: the day's writing is
  today minus yesterday, and a day spent cutting text falls out as a negative with no extra
  code. A stored delta cannot be reconciled back to the total.
- The total is `SUM(scenes.word_count)` over the project — the same number the project
  header already shows. Nothing new counts words.
- Written **on save**, not by a scheduled job: each scene save upserts today's row.
- A day is the **writer's** day. Timezone lives on the `User` (one working day across all
  their projects), defaults to the app timezone, and is editable in the profile.
- No backfill. History starts the day the feature ships; earlier dates simply have no row.
- **Finishing an import records one row.** An import writes in bulk and fires no model
  events, so without an explicit call the whole imported manuscript would land as a first
  snapshot whose day counts as zero.
- **Demo projects get a generated history.** The Melusine seeders synthesise a plausible
  past ending on the project's real current total. Invented history beside invented scenes
  is consistent; the no-backfill rule protects real projects, not the demo.

## Goals on the project

Three numbers, editable, not historicized — changing one re-draws the past:

- daily goal
- monthly goal
- total goal (the project's finished length)

## Chart on the project dashboard

Chart.js line chart — a new frontend dependency; nothing charting exists today.

- X: dates. Y: words.
- One coloured line for **words written that day** — the delta between snapshots, not the
  stored cumulative total. The grey daily-goal line is a per-day number, so the two cannot
  share a Y axis otherwise.
- One flat grey line for the daily goal, drawn only when a daily goal is set.
- Default range: the current month. The writer can pick another month, or a free range.
- Point labels show the day's figure.

The monthly and total goals have no line. They sit under the chart as progress readouts —
words this month against the monthly goal, current total against the total goal.

## Non-goals

- **Challenges** — a named window with a target and a par line ("a draft by September"). That
  is `word-count-challenges`, a follow-up draft that builds on these snapshots.
- Changing what a word is, or where it is counted from (`scenes.contents` only — see
  `documentation/word-count.md`).
- Per-scene, per-chapter or per-act history. The snapshot is project-wide.
- Streaks, reminders, notifications.
