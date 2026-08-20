# 02 — Window rules

## Scope

- `App\Support\ChallengeWindow` — readonly `from`/`to` (`CarbonImmutable`) plus
  `ChallengeWindow::for(Challenge $challenge, CarbonImmutable $today)`.
- The `ChallengeState` decision (`Upcoming` / `Running` / `Finished`) for a window against a
  given day.
- `totalDays` — inclusive day count.

**Not** in this task: words, par, or anything reading a snapshot (03). No views.

## Depends on

01.

## Key decisions

- Fixed (`None`): the stored dates, unchanged, whatever today is.
- Monthly: the whole calendar month containing today — **never clipped to `starts_on`**. When
  `starts_on` is still in a future month, the window is that month and the state is `Upcoming`.
  When `ends_on` is set and its month has passed, the window is `ends_on`'s month and the state
  is `Finished`.
- `Running` includes the last day. `Finished` starts the day after.
- Today always comes from `WriterDay::for($project->user)`; the caller passes it in, so the
  class stays pure and testable.

## Consult

`expanded/architecture.md` → *The window*.

## Tests

`tests/Unit/Support/ChallengeWindowTest.php`:

- monthly windows on the 1st and the last day of 28-, 30- and 31-day months;
- a monthly challenge starting in a future month → `Upcoming`, windowed on that month;
- the first month is not clipped to `starts_on`;
- a monthly challenge past its `ends_on` month → `Finished`, windowed on that month;
- a fixed window comes back unchanged before, during and after;
- last day is `Running`, next day is `Finished`;
- a user in a timezone with a DST change inside the window still gets the right `totalDays`.
