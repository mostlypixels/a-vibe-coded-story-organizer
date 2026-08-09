# 04 — The history service

The arithmetic the whole feature rests on. Highest-value tests in the plan.

## Scope

- `App\Support\DailyWordCount` — `date`, `total`, `written`.
- `App\Support\WordCountSeries` — a list of them, plus `writtenInRange()`,
  `writtenOn(CarbonImmutable)`, `currentTotal()`.
- `App\Services\WordCountHistory::series(Project, CarbonImmutable $from, CarbonImmutable $to): WordCountSeries`.

**Not** in this task: any controller or view. Nothing renders this yet.

## Depends on

02 (the table).

## Key decisions

Three rules, all binding:

- **A day with no row inherits the previous day's total and wrote 0.** No row means no save,
  which means no change — not missing data. The series has one entry per calendar day in the
  range, gaps included.
- **The delta needs the row *before* the range.** One extra query:
  `where('recorded_on', '<', $from)->latest('recorded_on')->first()`. Without it every month
  opens with a false spike.
- **With no earlier row, the previous total is 0** — not "the first day wrote nothing". A
  project genuinely had no words before its first row, so its first writing day counts in
  full. This is why no baseline row exists anywhere in the feature.

Two queries total, whatever the range length. Arithmetic in PHP, following
`app/Services/ProjectSearch.php`'s posture: the service owns the queries, the controller
resolves and authorizes.

The series carries its own aggregates so Blade never loops to add anything up.

## Consult

`expanded/architecture.md` → *The read path*

## Tests

`expanded/testing.md` → *`WordCountHistoryTest`*, all of it. The two that catch real bugs:

- A range starting **mid-history** uses the row before the range, not its own first row.
- **No row before the range at all** → the first day's `written` is its full total, not 0.
