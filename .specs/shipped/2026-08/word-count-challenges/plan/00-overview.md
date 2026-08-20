# Word count challenges — plan

A challenge is a window plus a target. Everything shown is derived from the shipped
`word_count_snapshots` rows; nothing about progress is stored.

## Execution order

| # | Task | Purpose |
| --- | --- | --- |
| 01 | `table-and-model` | `challenges` table, `Challenge` model, the two enums, factory |
| 02 | `challenge-window` | `ChallengeWindow` — fixed and monthly window rules |
| 03 | `challenge-progress` | `rebasedTotals()`, `ChallengeStanding`, `ChallengeProgress` |
| 04 | `crud` | routes, controller, Form Requests, create/edit forms |
| 05 | `progress-page-list` | running cards, upcoming rows, past table, empty state |
| 06 | `challenge-chart` | the three-dataset chart, wired into the running card |
| 07 | `archive` | `data/challenges.json`, export, import, validator, version 5 |
| 08 | `demo-seed` | Melusine gets one finished and one running challenge |
| 09 | `documentation` | writing-progress + archive-format guides |

02 and 03 are pure arithmetic with unit tests and no screen. 05 renders without 06, so a
chart that needs a second pass blocks nothing.

## Binding decisions

Settled in the grill or in `expanded/`. Later tasks must not re-open them.

- **Monthly recurrence ships in this pass.** One row; its month is derived, never materialised.
  No scheduler, no occurrence table.
- **A monthly challenge's first month is not clipped to `starts_on`.** Start on the 10th and
  you are behind par for that month. `ends_on` is optional on a monthly challenge and stops it
  after that month, keeping every finished month readable.
- **Par counts finished days only.** Day 1 opens at par 0. The par *line* still plots the
  end-of-day figure; the card reads yesterday's point. Full par arrives only once finished.
- **A challenge is Running through its last day**, Finished from the next day.
- **`written` is words written inside the window** — `WordCountSeries::writtenInRange()`. A
  window opening before the project's first snapshot needs no special case.
- **Negatives are shown.** A net cut reads `−2,300 of 50,000`, empty bar, danger colour.
- **Overshoot keeps counting.** `61,000 of 50,000`, bar full, *target reached* instead of a
  per-day figure.
- **Edits are silent.** No revisions, no warning, no lock. Changing a target re-scores the past.
- **Fixed windows cap at 366 days**, matching `ShowProgressRequest`. The cap does not apply to
  monthly.
- **No index route and no dashboard change.** The Progress page is the list.
- **Chart is a second component in `resources/js/word-count-chart.js`**, not a third variant of
  `x-word-count-chart`.

## Invariants every task preserves

- **`WriterDay::for($project->user)`** is the only source of "today". Never `now()`, never
  `auth()->user()`.
- **Authorization walks up to the project.** `ProjectPolicy@view` to read, `@update` to write,
  mirrored in every Form Request `authorize()`. Every new endpoint gets a 403 test.
- **No stored progress.** No cached totals, no verdict column, no occurrence rows.
- **Dates are `date`s, never timestamps** — same rule as `word_count_snapshots.recorded_on`.
- **Challenges are not revisioned** and never touch `HasRevisions` or `AutosavableFields`.
