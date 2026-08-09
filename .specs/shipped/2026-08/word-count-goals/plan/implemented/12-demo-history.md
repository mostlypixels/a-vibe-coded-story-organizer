# 12 — Demo history

Nothing depends on this. It can be dropped without disturbing the feature — what you lose is
a demo that can show the chart, and the fixture that exercises gap-filling and negative days.

## Scope

- `Database\Seeders\Concerns\SeedsWordCountHistory`, called by each Melusine seeder after
  `backfillSceneWordCounts()`.
- `App\Console\Commands\WordCountDemoHistoryCommand` — `word-count:demo-history {project}
  --days=60 --seed=`, a thin wrapper over the same generator (precedent: `ThemeRampCommand`).
- Melusine's two goals set in the seeder.

## Depends on

03 (for the project's real total; the generator does not call the recorder).

## Key decisions

- **Work forward from zero to the project's real total, never backward from it.** Scale the
  generated deltas so they sum to exactly `SUM(scenes.word_count)`, remainder on the last day.
  The final row **must** equal the live sum — a chart whose last point disagrees with the page
  header discredits the whole feature.
- **No leading zero row.** Under *before the first row the total was 0*, the first generated
  day is already counted in full.
- **Deterministic.** Seed from a hash of the project name so the three Melusine projects
  differ from each other and are identical between runs. `DatabaseSeederTest` asserts exact
  numbers, and paratest gives each process its own database — "it passed once" proves nothing.
- **It must produce, every time:** 2–3 rest days with no row, at least one negative day, days
  both sides of the daily goal, and a run starting before the current month (the fixture for
  the "row before the range" rule).
- One `upsert`, not 60 inserts. Back-dating goes through `WriterDay` for the project owner.
- **It is not a lie.** Melusine is fiction throughout; an invented history is consistent with
  invented scenes. The rule against inventing history protects real projects.

## Consult

`expanded/demo-history.md`

## Tests

- `DatabaseSeederTest`: every seeded project has the generator's exact set of dates, and the
  last snapshot equals the live `SUM(scenes.word_count)`.
- The command runs against one project and is idempotent on a second run.
- The generator does **not** replace `WordCountHistoryTest` — that stays on hand-built rows.
