# 05 — Challenges on the Progress page

## Scope

- `ProgressController::index` takes `ChallengeProgress` as a second dependency and adds
  `runningChallenges`, `upcomingChallenges`, `pastChallenges`.
- `resources/views/progress/index.blade.php` — a challenges section between the status strip
  and the range-picker card.
- `resources/views/components/challenge-card.blade.php` — a running challenge, chart slot
  left empty for 06.
- Upcoming rows, the past `x-table`, the empty line, the *New challenge* button and the
  per-card edit/delete icons.
- **Fix `x-progress-bar`** so a negative `value` renders an empty bar (clamp the percent to
  `[0, 100]`); today it produces a negative width.

**Not** in this task: the chart itself (06).

## Depends on

01, 02, 03, 04.

## Key decisions

- Layout and card content: `expanded/ui.md` → *Progress page layout*, *challenge-card*.
- Running cards ordered by nearest deadline; upcoming are one-line rows (window, target, par
  a day), no card, no chart.
- Past table: mixed across challenges, newest first, **12 rows total**, columns name / window
  / written of target / met-or-missed badge. A recurring challenge contributes its finished
  months, never the current one.
- Ahead-or-behind is the headline figure — accent when `delta >= 0`, danger when negative.
- `perDayNeeded` shows only while running with words still to write; otherwise *target
  reached*.
- No challenges at all → one line of copy plus the button, not an empty table.

## Consult

`expanded/ui.md`; `expanded/architecture.md` → *Routes and controllers*.

## Tests

Extend `tests/Feature/ProgressPageTest.php`:

- running, upcoming and past each render in their own section;
- the past table caps at 12 rows and includes a monthly challenge's finished months but not
  its current one;
- no challenges → the empty line, no table;
- a net-cut challenge renders the negative figure and an empty (not broken) bar;
- an overshooting challenge shows *target reached* and the raw total above the target;
- a non-owner still gets 403 on the page.
