---
status: shipped
shipped: 2026-08-09
planned: 2026-08-08
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
- **Before a project's first row, its total was 0.** So a new project's first writing day is
  counted in full, and nothing has to write a baseline row.
- **Snapshots travel in the export.** A backup that drops the writing history is not a
  backup of the project — and carrying the rows is what removes the need for an
  import-time baseline.
- **Demo projects get a generated history.** The Melusine seeders synthesise a plausible
  past ending on the project's real current total. Invented history beside invented scenes
  is consistent; the rule protects real projects, not the demo.

## Goals on the project

Two numbers, editable, not historicized — changing one re-draws the past:

- **daily goal** — a rhythm
- **total goal** — the project's finished length, a destination

Both are open-ended. A goal with a *window* — "20,000 this month" — is a challenge, not a
project goal, and lives in `word-count-challenges`.

## Progress page (Tools ▾ → Progress)

Its own page, not the dashboard — the dashboard is already the heaviest page in the app, and
`word-count-challenges` will want to live here too. Chart.js is a new frontend dependency;
nothing charting exists today.

- X: dates. Y: words.
- **Bars** for words written that day — the difference between snapshots, derived at read
  time, never stored. Each day is its own quantity, so bars, not a line.
- One flat grey **line** for the daily goal over them, drawn only when a daily goal is set.
- Default range: the current month. The writer can pick another month, or a free range.
- A status strip above the chart, always showing *now* regardless of the range: today's
  words against the daily goal, and the current total against the total goal.

## Progress card on the dashboard

The same two progress bars, plus a compact 14-day chart with no axes, linking through to the
Progress page. Fourteen rolling days rather than the current month, so the card still reads
as a chart on the 1st.

## Non-goals

- **Challenges** — a named window with a target and a par line ("a draft by September"). That
  is `word-count-challenges`, a follow-up draft that builds on these snapshots.
- Changing what a word is, or where it is counted from (`scenes.contents` only — see
  `documentation/word-count.md`).
- Per-scene, per-chapter or per-act history. The snapshot is project-wide.
- Streaks, reminders, notifications.
