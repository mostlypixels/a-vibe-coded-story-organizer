# 03 — Progress arithmetic

## Scope

- `WordCountSeries::rebasedTotals(): Collection<int, int>` — the running sum of `written`
  from the first day of the range.
- `App\Support\ChallengeStanding` — readonly, every field the card shows.
- `App\Services\ChallengeProgress` — `standing()` and `pastOccurrences()`.

**Not** in this task: controllers, views, or the chart. Nothing renders yet.

## Depends on

01, 02.

## Key decisions

- Field list and formulas: `expanded/architecture.md` → *Standing*.
- **Par uses finished days only** (`parDays`), so day 1 opens at par 0 and full par arrives
  only once finished. `parTotals` (for the chart line) still holds the end-of-day figure per
  day.
- `written` is `WordCountHistory::series($project, from, to)->writtenInRange()` — it may be
  negative, and no floor is applied.
- `daysLeft` counts today. `perDayNeeded` is `null` when the challenge is not running and 0
  when the target is already met.
- `met` is `written >= target`, inclusive, and `null` unless finished.
- `pastOccurrences()` caps at 12, newest first, and mixes nothing: it is per challenge. For a
  monthly challenge it uses **one** series over every completed month and slices it in PHP.
- Two queries per standing is accepted (`expanded/architecture.md` → *Query cost*). Do not
  batch across challenges.

## Consult

`expanded/architecture.md` → *Standing*, *One addition to the shipped series*, *Query cost*.

## Tests

`tests/Unit/Services/ChallengeProgressTest.php`, snapshot rows written directly, `WriterDay`
frozen with `CarbonImmutable::setTestNow()` and a non-UTC owner timezone — the full case table
in `expanded/testing.md`, plus:

- par is 0 on the morning of day 1, and one day short of the target on the last day;
- par equals the target exactly once finished;
- `pastOccurrences()` of a monthly challenge returns one entry per completed month, capped
  at 12, and runs one series query (assert with `DB::listen` or a query count).

Add a `WordCountSeries::rebasedTotals()` case to the shipped `WordCountHistoryTest`.
