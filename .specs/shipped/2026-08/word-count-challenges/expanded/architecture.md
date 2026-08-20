# Architecture

One service turns a `Challenge` row plus the existing snapshot series into a **standing**.
Nothing writes anything at read time.

## The window

`App\Support\ChallengeWindow` — `readonly` value object (`from`, `to`, both
`CarbonImmutable`), built by `ChallengeWindow::for(Challenge $challenge, CarbonImmutable
$today)`.

| `recurrence` | Window |
| --- | --- |
| `None` | `starts_on` … `ends_on`, as stored |
| `Monthly` | the **whole calendar month** containing `$today` (or containing `starts_on`, when that is still in the future), clipped to end at `ends_on`'s month when one is set |

**A monthly challenge's first month is not clipped to `starts_on`.** A writer who sets
"20,000 a month" on the 10th is behind par for that month — which is true, and it saves a
pro-rating rule that would then have to be explained on the page.

`$today` is always `WriterDay::for($project->user)`. Never `auth()->user()`, never `now()`.

## Standing

`App\Services\ChallengeProgress`

```php
public function standing(Challenge $challenge, CarbonImmutable $today): ChallengeStanding
/** @return Collection<int, ChallengeStanding> newest window first, at most $limit */
public function pastOccurrences(Challenge $challenge, CarbonImmutable $today, int $limit = 12): Collection
```

`App\Support\ChallengeStanding` (readonly) carries everything the view needs, so Blade never
calculates:

| Field | Rule |
| --- | --- |
| `window` | `ChallengeWindow` |
| `state` | `ChallengeState`: `Upcoming` / `Running` / `Finished` |
| `totalDays` | inclusive day count of the window |
| `elapsedDays` | days from the start through today, clamped to `[0, totalDays]` |
| `parDays` | **finished** days only — `elapsedDays − 1` while running, `totalDays` once finished, 0 while upcoming |
| `written` | `WordCountHistory::series($project, from, to)->writtenInRange()` |
| `target` | `target_words` |
| `par` | `(int) round($target * $parDays / $totalDays)` — computed, never stored |
| `delta` | `written − par`; negative is behind |
| `remaining` | `max(0, target − written)` |
| `daysLeft` | `totalDays − elapsedDays + 1` while running (today still counts), else 0 |
| `perDayNeeded` | `daysLeft > 0 ? (int) ceil(remaining / daysLeft) : null` |
| `met` | `Finished && written >= target` — `null` unless finished |
| `dailyTotals` | rebased cumulative words, one per day, for the climbing line |
| `parTotals` | `round($target * $d / $totalDays)` for each day *d*, for the par line |

**Par counts only finished days.** On the morning of day 1 par is 0, so the writer opens a
challenge level, not behind — the same forgiveness the shipped `currentStreak()` gives today
("today extends a streak but never breaks one"). The par *line* on the chart is unchanged:
its point for day *d* is the end-of-day figure `round($target * $d / $totalDays)`, and the
card simply reads yesterday's point. The last day therefore shows par one day short of the
target, and the challenge reaches full par only once it is finished.

**`written` is words written *inside* the window**, which is what `writtenInRange()` already
means: it sums per-day deltas, and the day before the window supplies the baseline. A
challenge that opens before the project's first snapshot therefore starts at 0 with no
special case — the "previous total was 0" rule does the work.

Future days inside a running window contribute a 0 delta, so `dailyTotals` flattens after
today. The view stops the climbing line at today rather than drawing that flat tail
(see [ui](ui.md)).

### One addition to the shipped series

`WordCountSeries::rebasedTotals(): Collection<int, int>` — the running sum of `written` from
the first day of the range. The climbing line is exactly this, and putting it on the series
keeps the "Blade never loops to add anything up" rule the class already documents.

### Query cost

Two queries per standing (the range rows and the row before it). Charts are drawn only for
running and upcoming challenges, so a project with three live challenges adds six queries to
the Progress page. Past occurrences of a **monthly** challenge come from **one** series
covering every completed month, split by month in PHP — twelve months is twelve slices of an
array, not twelve queries.

**Rejected: one `IN`-query for every challenge at once.** The windows overlap arbitrarily and
can sit years apart, so a single range would materialise a per-day array covering all of them
to serve a handful of slices.

## Routes and controllers

```
Route::resource('projects.challenges', ChallengeController::class)
    ->only(['create', 'store', 'edit', 'update', 'destroy'])
    ->shallow();
```

Beside `projects.plotlines` in the `auth` group, same shallow convention.

**No `index` action.** The Progress page *is* the list — a second page listing the same
cards, one click away, would be the same screen twice. `ProgressController::index` takes
`ChallengeProgress` as a second constructor dependency and adds three view keys:
`runningChallenges`, `upcomingChallenges`, `pastChallenges`.

`ChallengeController` follows `PlotlineController` exactly: `create`/`edit` return a form,
`store`/`update` delegate validation to a Form Request and redirect to
`projects.progress`, `destroy` authorizes `update` on the project and redirects there too.
No `RecordsManualRevisions` — challenges are not revisioned.

Authorization is `ProjectPolicy` throughout: `view` for the Progress page, `update` for every
write. `authorize()` in each Form Request mirrors it, resolving the project from
`$this->route('project')` (store) or `$this->route('challenge')->project` (update).

### Validation

`StoreChallengeRequest` / `UpdateChallengeRequest`, same rules:

```php
'name'         => ['required', 'string', 'max:255'],
'recurrence'   => ['required', Rule::enum(ChallengeRecurrence::class)],
'starts_on'    => ['required', 'date'],
'ends_on'      => ['required_if:recurrence,none', 'nullable', 'date', 'after_or_equal:starts_on'],
// nullable for `monthly` means "runs until stopped"; a date means "stop after that month"

'target_words' => ['required', 'integer', 'min:1', 'max:10000000'],
```

Plus a closure on `ends_on` capping a fixed window at **366 days**, the same cap
`ShowProgressRequest` puts on the chart range and for the same reason: the standing
materialises one entry per day in PHP.

A monthly challenge **may** carry an `ends_on` — that is how a recurring challenge is stopped
without deleting a year of record. Empty means "runs until deleted". The 366-day cap applies
only to `none`; a recurring challenge is a series of month-long windows, not one long one.

No cross-check between `target_words` and the project's `daily_word_goal`. They are different
intents, not a contradiction — the same reasoning that left the two project goals uncrossed.

## Where things live

| Concern | Location |
| --- | --- |
| The row | `app/Models/Challenge.php` |
| Recurrence, state | `app/Enums/ChallengeRecurrence.php`, `app/Enums/ChallengeState.php` |
| Window derivation | `app/Support/ChallengeWindow.php` |
| Standing arithmetic | `app/Services/ChallengeProgress.php` |
| Standing value object | `app/Support/ChallengeStanding.php` |
| Cumulative helper | `app/Support/WordCountSeries.php` (`rebasedTotals()`) |
| CRUD | `app/Http/Controllers/ChallengeController.php` |
| Validation | `app/Http/Requests/StoreChallengeRequest.php`, `UpdateChallengeRequest.php` |
| Listing | `app/Http/Controllers/ProgressController.php` |
| Archive | `app/Services/StaticSiteExporter.php`, `app/Services/Import/ProjectGraphImporter.php`, `ArchiveValidator` |
| Demo data | `database/seeders/Concerns/SeedsChallenges.php` |

## Documentation to update

- `documentation/features/writing-progress.md` — a *Challenges* section: window derivation,
  par is computed, monthly months are derived not stored, nothing is historicized.
- `documentation/export-import/archive-format.md` — `data/challenges.json` and the version-5 bump.
