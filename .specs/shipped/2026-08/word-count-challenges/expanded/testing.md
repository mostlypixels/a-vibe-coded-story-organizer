# Testing

`tests/Feature/ChallengeTest.php` — CRUD, following `PlotlineTest`:

- create / update / delete round trip; redirect lands on `projects.progress`.
- a non-owner gets **403** on create, store, edit, update and destroy.
- `ends_on` before `starts_on`, a span over 366 days, `target_words` of 0, and a `none`
  challenge with no `ends_on` all fail validation.
- switching a fixed challenge to `monthly` nulls its `ends_on`.
- deleting the project deletes its challenges (cascade).

`tests/Unit/Services/ChallengeProgressTest.php` — the arithmetic, with snapshot rows written
directly and `WriterDay` frozen via `CarbonImmutable::setTestNow()` on a non-UTC user
timezone:

| Case | Expectation |
| --- | --- |
| window entirely before the first snapshot | `written` 0, no error |
| window opens before the first snapshot, ends after | counts from 0, not from the first row's total |
| window opens mid-history | the day before the window supplies the baseline; day 1 is not a spike |
| a cut day inside the window | `written` drops; `dailyTotals` steps back |
| gap days | inherit the previous total, `written` 0 |
| `elapsedDays` on the first day | 1, so par is one day's worth, not 0 |
| `elapsedDays` after the end | clamped to `totalDays`; par equals target exactly |
| upcoming challenge | `elapsedDays` 0, par 0, `state` `Upcoming`, `met` null |
| finished, `written == target` | `met` true (the boundary is inclusive) |
| `perDayNeeded` on the last day | `remaining`, not a division by zero |
| `remaining` 0 with days left | `perDayNeeded` 0, no negative |

`tests/Unit/Support/ChallengeWindowTest.php`:

- monthly window on the 1st and on the last day of a 28-, 30- and 31-day month;
- a monthly challenge whose `starts_on` is in a future month is `Upcoming`, windowed on that
  month, not on today's;
- the first month is **not** clipped to `starts_on`;
- a fixed window is returned unchanged whatever today is;
- DST: a user in a zone with a transition inside the window still gets the right `totalDays`.

`tests/Feature/ProgressPageTest.php` (extend the shipped file):

- running, upcoming and past challenges each render in their own section;
- the past table caps at 12 rows;
- a project with no challenges renders the empty line, not an empty table;
- a monthly challenge's completed months appear as past rows.

`tests/Feature/CreateChallengesMigrationTest.php` — the column list, the cascade and the
`(project_id, starts_on)` index, following `CreateWordCountSnapshotsMigrationTest`.

Archive coverage, extending the shipped word-count files:

- `WordCountGoalsArchiveTest` (or a sibling) — challenges are written to
  `data/challenges.json` and restored on import, with `null` `ends_on` surviving;
- `ImportRoundTripTest` — a version-4 archive with no challenges file imports cleanly;
- `ArchiveValidator` rejects a challenges entry missing a required key.

`tests/Feature/DatabaseSeederTest.php` — Melusine gets exactly one finished and one running
challenge, and the finished one reads *met*. Deterministic, since the targets derive from
`WordCountHistoryGenerator::plan()`.

`resources/js/challenge-chart.test.js` — Vitest over the exported config builder, no DOM
chart: three datasets, the climbing line `null` after today, `y1.max` equal to the target,
negative bars taking the danger colour. Follows `word-count-chart.test.js`.
