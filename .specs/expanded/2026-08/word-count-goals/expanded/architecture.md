# Architecture

Two halves that never call each other: a **recorder** that writes one row a day, and a
**reader** that turns rows into a chart series.

## The writer's day

`App\Support\WriterDay` — the only place a local date is derived.

```php
WriterDay::for(User $user): CarbonImmutable   // start of the user's current local day
WriterDay::dateFor(User $user): string        // 'Y-m-d', what recorded_on stores
```

Implementation is `now($user->timezone ?? config('app.timezone'))`. One helper because the
recorder, the chart's default range, and "is today's row already there?" must agree
byte-for-byte; three inline `now()->timezone(...)` calls would not survive the first
timezone bug.

> [!WARNING]
> The timezone comes from the **project's owner**, not `auth()->user()`. They are the same
> person today, but an import, an artisan command or a future shared project would silently
> file rows under the wrong day. `$project->user->timezone` is the rule.

## The write path

`App\Services\WordCountSnapshotRecorder::record(Project $project): void`

1. `$total = $project->sceneQuery()->sum('word_count')` — the same query
   `ProjectController::show()` already runs for the header, so there is one definition of
   "the project's words".
2. `WordCountSnapshot::upsert([...], ['project_id', 'recorded_on'], ['word_count'])`.

**`upsert`, not `updateOrCreate`.** One atomic statement on SQLite and MySQL alike. Two
autosaves landing in the same millisecond would race `updateOrCreate` into a unique-key
violation; `upsert` resolves it in the database.

Wired from model events, for the reason `documentation/word-count.md` gives for the
`Scene::saving` hook — a controller-level call misses `RevisionReverter`, so Undo would
stop recording:

| Event | Guard | Why |
|---|---|---|
| `Scene::saved` | `wasChanged('word_count')` | skips saves that touched only `notes`, `status`, `position` |
| `Scene::deleted` | — | deleting is writing; the total drops |
| `Chapter::deleted` | — | its scenes cascade at the DB level, firing no `Scene::deleted` |
| `Act::deleted` | — | same, two levels down |

`saved`, not `saving` — the recorder sums the table and needs the row already written.
This is the opposite of the `word_count` hook's choice, and for the opposite reason.

**`Project::deleted` is not in the list.** The rows cascade away with the project; there is
nothing to record about a project that no longer exists.

### Cost

One `SUM` and one `upsert` per content save. The `SUM` is the query the dashboard header
already runs on every page load, so it is a known quantity at this app's scale; the guard
keeps it off saves that changed no words. Do not "optimise" it into a denormalised
`projects.word_count` — `documentation/word-count.md` benchmarked that decision and
rejected it.

### What deliberately does not record

Anything that writes with `DB::table()`: the seeders' `BackfillsSceneWordCounts`, the
`scenes.word_count` migration backfill, and any future bulk path. That is the mechanism
behind "no history before you turn it on", not an oversight — but it is why the Melusine
demo projects open on an empty chart.

## The read path

`App\Services\WordCountHistory` — template is `app/Services/ProjectSearch.php`: the
controller resolves and authorizes, the service owns the queries and the arithmetic.

```php
public function series(Project $project, CarbonImmutable $from, CarbonImmutable $to): WordCountSeries
```

One query (`whereBetween('recorded_on', …)->orderBy('recorded_on')`), then arithmetic in
PHP. Returns `App\Support\WordCountSeries`, a list of `App\Support\DailyWordCount`
(`date`, `total`, `written`) with **one entry per calendar day in the range**, gaps included.

Three rules, all binding:

- **A day with no row inherits the previous day's total, and wrote 0.** No row means no
  save, which means no change — not missing data. This is what lets the chart draw a
  continuous line without the reader guessing.
- **The delta needs the row *before* the range.** Fetching only the range makes the first
  day of every month appear to have written everything since the 1st. The service fetches
  one extra row: `where('recorded_on', '<', $from)->latest('recorded_on')->first()`.
- **When no earlier row exists, the first day's `written` is 0.** Those words predate
  tracking and belong to nobody's day. Recorded in [open-questions](open-questions.md).

`WordCountSeries` also carries the aggregates the readouts need — `writtenInRange()`,
`currentTotal()` — so Blade never loops to add anything up.

## Routes and controllers

No new routes. `ProjectController::show()` gains the chart.

```php
public function show(Project $project, ShowProjectRequest $request, RecentlyEdited $recentlyEdited, WordCountHistory $history): View
```

- Range comes in as `?from=&to=` query parameters, validated by a new
  `App\Http\Requests\ShowProjectRequest` — a GET Form Request, precedent
  `SearchRequest`. `authorize()` mirrors `$this->user()->can('view', $this->route('project'))`.
- Rules: `from`/`to` both `nullable|date`, `to` `after_or_equal:from`, and a `max:366`-day
  span so a hand-edited URL cannot ask for a decade of points.
- Default range when absent: the current month **in the owner's timezone** —
  `WriterDay::for($project->user)->startOfMonth()` to `->endOfMonth()`. Not
  `now()->startOfMonth()`.
- Range resolution stays in the controller, per CLAUDE.md ("index-page filtering… in the
  controller"), and is handed to the service as two dates.

**Rejected: a JSON endpoint the chart fetches.** It buys in-place range switching at the
cost of a second authorized surface and a second serialization of the same data. Revisit if
the full-page reload feels wrong — [open-questions](open-questions.md).

## Where the rest lives

| Concern | Location |
|---|---|
| Local-date resolution | `app/Support/WriterDay.php` |
| Writing a snapshot | `app/Services/WordCountSnapshotRecorder.php` |
| Model-event wiring | `booted()` in `Scene`, `Chapter`, `Act` |
| Series + delta arithmetic | `app/Services/WordCountHistory.php` |
| Series value objects | `app/Support/WordCountSeries.php`, `app/Support/DailyWordCount.php` |
| Goal validation | `app/Http/Requests/UpdateProjectRequest.php` (three new rules) |
| Timezone validation | `app/Http/Requests/ProfileUpdateRequest.php` (`['nullable', 'timezone']`) |
| Range validation | `app/Http/Requests/ShowProjectRequest.php` |

Authorization is `ProjectPolicy` throughout — `view` for the dashboard, `update` for the
goals on the project form. Nothing here is a singleton setting, so the `CrawlerSetting`
exception does not apply.

## Documentation to update

- `documentation/word-count.md` — a *History and goals* section pointing at the new deep dive.
- `documentation/word-count-goals.md` — the deep dive: the writer's day, the cumulative-vs-delta
  rule, what does not record.
- `documentation/architecture.md` — a compact entry linking both.
