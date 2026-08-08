# Demo history

A generator that gives a project a plausible past, so the chart has something to draw
before anyone has written a word in it.

## Why this is not a lie

"No history before you turn it on" protects **real** projects: never invent writing a person
did not do. Melusine is fiction from the title down — its scenes, its characters, its
timeline are all invented, and an invented writing history is consistent with that, not a
breach of the rule. Write this distinction down; it is exactly the kind of decision someone
re-litigates in six months.

## Shape

Mirror `Database\Seeders\Concerns\BackfillsSceneWordCounts` — the existing precedent for
"seeding is a write path, model events are off, so do it explicitly once the tree exists".

- `Database\Seeders\Concerns\SeedsWordCountHistory` — the trait each Melusine seeder calls
  after `backfillSceneWordCounts()`.
- `App\Console\Commands\WordCountDemoHistoryCommand` — `word-count:demo-history {project}
  --days=60 --seed=`, a thin wrapper for an ad-hoc project you want to eyeball. Same
  generator, same code path. Precedent for a dev-only command: `ThemeRampCommand`.
- Rows go in with one `WordCountSnapshot::upsert()`, not 60 inserts.

## The generation rule

**Work forward from zero to the project's real total — never backward from it.**

1. `$total = $project->sceneQuery()->sum('word_count')` — the number the header shows.
2. Generate `$days` daily deltas, then scale so they sum to **exactly** `$total`, putting
   the rounding remainder on the last day.
3. Cumulative-sum them into one row per day, ending **today** at `$total`.
4. Write one extra row of `0` on the day *before* the range.

> [!WARNING]
> Step 3's last row must equal the live `SUM(scenes.word_count)`. If the chart's final point
> disagrees with the word count in the page header, a reviewer stops trusting the whole
> feature — and they are right to. This is the one assertion the seeder test must make.

Step 4 exists because of [open-questions](open-questions.md) → 2: with no earlier row, the
first day's `written` is 0 and its writing vanishes. A zero-total row the day before makes
day one honest.

## Determinism

`DatabaseSeederTest` asserts on seeded output; a random history makes `composer test` flaky,
and paratest gives each process its own database, so "it passed once" proves nothing.

- Seed the generator explicitly (`mt_srand()` or a seeded Faker instance), defaulting to a
  hash of the project name — the three Melusine projects then look different from each
  other and identical between runs.
- `--seed=` overrides it for the command.

## Make it exercise the edge cases

This is the payoff beyond "the demo looks nice": the generator is a fixture that hits the
paths [testing](testing.md) describes abstractly. It must produce, every time:

| Shape | Exercises |
|---|---|
| 2–3 rest days with no row | gap-filling, and `written` = 0 on a skipped day |
| At least one negative day | "deleting counts as negative" |
| Days both above and below the daily goal | the grey line means something |
| A run starting well before the current month | the "row before the range" delta rule — the bug that makes every month open with a false spike |

Also set Melusine's three goals in the seeder. Without a `daily_word_goal` the chart draws a
coloured line and no grey one, which is half the feature.

## Timezone

Back-dating goes through `WriterDay` for the **project owner**, like every other write. A
generator that back-dates in UTC puts the demo history off by one for any non-UTC user and
sends someone hunting a phantom bug in the recorder.

## What it does not do

**It does not replace tests.** Generated data is a fixture, not an assertion.
`WordCountHistoryTest` stays on hand-built rows with known answers. The seeder test asserts
only the two invariants that make the demo trustworthy: one row per day in the range, and a
final total equal to the live `SUM`.
