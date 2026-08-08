---
status: draft
---

# Word Count Challenges

A writer commits to a target inside a window — "a first draft by the end of the summer",
"finish act 2 by March". A standing daily goal (`word-count-goals`) cannot say *am I on
track?*, because it has no end date to be behind against.

Depends on `word-count-goals`: challenges read its daily snapshots and add nothing to how
words are counted or stored. Three things that spec settled and this one must not re-open:

- The stored row is the project's **cumulative total** on a writer-local date. A challenge's
  climbing-to-target line is that total, rebased to the challenge's start; its per-day bars
  are the deltas. Both come off the same `WordCountSeries`.
- **Before a project's first row, its total was 0.** A challenge whose window opens before
  that row measures from 0, which is correct for a project that started inside the app and
  wrong for nothing else — imported projects bring their own history.
- The day boundary is the project owner's timezone, resolved once in `WriterDay`. A
  challenge's start and end dates use the same rule or the last day is off by one.
- The **Progress** page (Tools ▾) already exists and owns the chart component in `full` and
  `compact` variants. Challenges plug in there rather than opening a page of their own.

## Goals

- A challenge on a project: name, start date, end date, target words.
- **A recurring monthly goal is a challenge**, not a project goal — "20,000 this month" and
  "50,000 in November" are the same object. `word-count-goals` dropped its monthly goal for
  this reason, so a recurring window has to land here.
- Progress from the existing daily snapshots: words so far, par for today, ahead/behind.
- Par is **computed** (`target / days`), never configured.
- Several challenges per project; they may overlap.
- A finished challenge stays readable — it is a record, not a setting.

## Non-goals

- Changing what a word is, or how snapshots are written — that is `word-count-goals`.
- Challenges across several projects at once.
- Anything social: sharing, badges, leaderboards, buddies.
- Syncing with, or importing from, any external writing-challenge site.

## Rough approach

- A `challenges` table owned by a `Project`; authorization walks up to it as usual.
- Progress is derived, never stored — the snapshot rows are the source of truth.
- Reuse the `word-count-goals` chart rather than building a second one: a challenge is a
  date range plus a target, which is the shape that chart already draws.

## Open ends

- Does a challenge count *all* the project's words, or only words written inside its
  window? The usual reading is the second — the counter starts at zero on day 1.
- What happens to a challenge whose window is changed once it has started?
- Do challenges belong to the project or to the user? One writer running the same challenge
  across two projects is a real case, but it is listed as a non-goal above — confirm.
- What does a challenge show when its window starts before the project's first snapshot?
  Refuse to create it, or show partial progress with the gap marked?
- Should the demo-history generator (`word-count-goals`) also seed a finished challenge and
  a running one, so the demo shows both states?
