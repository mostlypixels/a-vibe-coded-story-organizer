# 08 — Demo challenges

## Scope

- `Database\Seeders\Concerns\SeedsChallenges`, beside `SeedsWordCountHistory`.
- Called from the Melusine seeders after the history is generated.

**Not** in this task: any change to `WordCountHistoryGenerator` or the snapshot history itself.

## Depends on

01, 03.

## Key decisions

- Two challenges: one **finished** over the calendar month that ended before today, and one
  **running** monthly challenge for the current month.
- Targets are **derived from the generated history**, not hard-coded, so the finished one
  always reads *made it* and the running one shows a believable ahead-or-behind on whatever
  day the seeder runs.
- Deterministic, because `WordCountHistoryGenerator::plan()` is.
- Fictional projects only — carry the same warning the history generator has.

## Consult

`expanded/data-model.md` → *Seeding*; `database/seeders/Concerns/SeedsWordCountHistory.php`.

## Tests

Extend `tests/Feature/DatabaseSeederTest.php`: Melusine has exactly one finished and one
running challenge, and the finished one's standing reads met.
