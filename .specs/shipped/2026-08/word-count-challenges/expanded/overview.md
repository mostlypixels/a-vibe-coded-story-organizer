# Overview

A challenge is a **window plus a target**: name, start, end, words. Everything else — words so
far, par, ahead/behind, the finished verdict — is arithmetic over the `word_count_snapshots`
rows `word-count-goals` already writes. Nothing new counts words.

## Goals

- CRUD a challenge on a project; several may run at once and may overlap.
- A **monthly recurring** challenge ("20,000 every month") is the same object with a
  recurrence rule — `word-count-goals` deleted its monthly goal expecting this.
- Show, for the current window: words written inside it, par for today, ahead/behind,
  words-per-day still needed.
- A past challenge stays readable with its verdict.
- Everything derived. No stored progress, no nightly job, no materialised occurrence rows.

## Non-goals

- Per-book challenges. Snapshots are project-wide; a per-book series does not exist.
- Cross-project or per-user challenges. A challenge belongs to one project.
- Changing counting, snapshot writing, or the day boundary (`word-count-goals` owns all three).
- Social anything; external-site sync; reminders or notifications.

## Decisions the source spec left open

| Question | Answer |
| --- | --- |
| Whole project or one book? | Whole project. |
| All words, or words in the window? | **In the window** — a challenge counts from 0 on day 1. |
| Window edited mid-run? | Allowed, silently. Progress is derived, so it simply re-reads. |
| Project or user owned? | Project, as the spec's non-goal already says. |
| Window opens before the first snapshot? | Allowed. Before the first row the total was 0, so the rebase is already correct — no gap marking, no refusal. |
| Seed demo challenges? | Yes: Melusine gets one finished and one running. |

## User stories

- *"50,000 in November."* Fixed window, 30 days, par 1,667/day.
- *"A first draft by the end of the summer."* Fixed window, months long, target = the draft's length.
- *"20,000 every month."* Monthly recurrence; each calendar month is its own occurrence with the same target.
- *"How did July go?"* A finished occurrence keeps its numbers and reads met or missed.

## Acceptance criteria

- A challenge whose window is `[s, e]` reports `written = series(s, e).writtenInRange()`.
- Par at the end of day *d* of *N* is `round(target × d / N)`; par is never stored or editable.
- A challenge that starts tomorrow shows as upcoming with no bars and no verdict.
- A monthly challenge shows the *current* calendar month's occurrence; earlier months are listed as records.
- Deleting a project deletes its challenges; a non-owner gets 403 on every route.
- Challenges travel in the export archive and restore on import.
