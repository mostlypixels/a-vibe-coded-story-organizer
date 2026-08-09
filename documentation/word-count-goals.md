# Word count goals

Two open-ended targets per project — a daily rhythm and a total destination —
plotted against the project's writing history on its Tools ▸ Progress page,
and on the dashboard, where a 31-day chart and the goal bars share the site's
9-3 split.

## The writer's day

`App\Support\WriterDay` is the only place a writer's local date is derived,
from `$user->timezone` (`null` falls back to `config('app.timezone')`).

- **Always resolve from the resource's owner, never `auth()->user()`.** They
  are the same person for an autosave, but an import or an artisan command
  runs as somebody else, or as nobody. `ProgressController` is the one
  exception — see `documentation/word-count.md`'s dashboard-card deviation for
  why `auth()->user()` is safe there specifically.
- The day, not the server's zone, decides which row a save lands on. A writer
  in `Pacific/Auckland` and one in `America/Los_Angeles` can be mid-write on
  different calendar dates at the same instant.

## Snapshots are cumulative, never a delta

`word_count_snapshots` holds one row per `(project_id, recorded_on)`: the
project's `SUM(scenes.word_count)` at the moment it was written, not how many
words that day added.

- **A stored delta cannot be reconciled after an edit to old text.** Deltas
  are derived at read time, in `App\Services\WordCountHistory`, and thrown
  away — nothing persists them.
- The unique index is written with `upsert`, never `updateOrCreate`: two
  autosaves in the same millisecond would race `updateOrCreate` into a
  constraint violation. `upsert` is one atomic statement.
- `App\Models\WordCountSnapshot::recordedOn()` is an `Attribute`, not a `date`
  cast. A cast writes `'Y-m-d 00:00:00'`; the recorder's `upsert` writes the
  bare `'Y-m-d'` from `WriterDay::dateFor()`. Two formats in one column broke
  the unique key and dropped a range query's last day before this fix.

## Before the first row, the total was 0

No baseline row, no `is_baseline` column, no `Project::created` hook, no
migration backfill. `App\Services\WordCountHistory::totalBefore()` returns 0
when a range starts before the project's first snapshot, and that is the
whole mechanism.

This replaced an earlier design where the first snapshot counted as "0
written" — which lost a new project's first writing day forever, because a
day can't both be the baseline and count its own words. A baseline row dated
*today* also collides with same-day writing: the recorder's `upsert`
overwrites it and the hole reopens the moment the writer saves.

## What does not record, and why

- **Bulk writes use `DB::table()`, never `$model->save()`.** Inherited from
  `documentation/word-count.md`: a model save would fire `Scene`'s hooks per
  row. `database/seeders/Concerns/SeedsWordCountHistory` exists because of
  this — it writes plausible history directly, since seeding never touches
  the model layer.
- **An import restores snapshot rows as a bulk insert**
  (`ProjectGraphImporter::importWordCountSnapshots()`), never through
  `WordCountSnapshotRecorder`. The restored rows *are* the history; recording
  on top of them would add a same-day row and defeat the point of carrying
  them across.
- **An archive with no `data/word-count-snapshots.json`** — every export
  written before this feature — imports as "no history", not an error.
  Goals travel the same way: an absent `daily_word_goal` /
  `total_word_goal` in `project.json` imports as `null`.

## The chart draws bars, and the status strip ignores the range

- **Bars for the day's writing, a flat dashed line for the daily goal.** Each
  day is its own quantity; a line would imply the value flows between two
  points, so a day the writer cut words would read as continuous rather than
  as one bar under the axis. A cut day's bar uses the danger color token
  instead of the normal bar color. See `resources/js/word-count-chart.js`.
- **No cumulative view.** The total goal is read from the status strip and the
  dashboard's progress bars, not from the chart.
- **The status strip always shows *now*, whatever range the chart is on.**
  `ProgressController::index()` computes `writtenToday` and `totalWords`
  independent of the submitted `from`/`to`. A per-range figure has no defined
  meaning over, say, an 80-day span — "words written this range" and "words
  written today" would silently become the same label for different numbers.
- `totalWords` reads the live `SUM(scenes.word_count)`, not the last snapshot
  row — the strip must never disagree with the project header while a save is
  still catching up to a snapshot write.

## Where things live

| Concern | Location |
| --- | --- |
| The writer's local day | `App\Support\WriterDay` |
| Snapshot row | `App\Models\WordCountSnapshot` |
| Writing the row | `App\Services\WordCountSnapshotRecorder`, called from `Scene::booted()` |
| Reading a range | `App\Services\WordCountHistory`, returning `App\Support\WordCountSeries` of `App\Support\DailyWordCount` |
| Goals | `projects.daily_word_goal` / `total_word_goal`, nullable, no default |
| Progress page | `App\Http\Controllers\ProgressController`, `resources/views/progress/index.blade.php` |
| Range picker validation, 366-day cap | `App\Http\Requests\ShowProgressRequest` |
| Chart | `x-word-count-chart`, `resources/js/word-count-chart.js` (`full` / `compact` variants) |
| Dashboard card | `resources/views/projects/show.blade.php` (Progress panel) |
| Export / import | `App\Services\StaticSiteExporter::addWordCountSnapshots()`, `App\Services\Import\ProjectGraphImporter::importWordCountSnapshots()` |
| Demo history | `Database\Seeders\Concerns\SeedsWordCountHistory`, `word-count:demo-history` artisan command |

> [!NOTE]
> Range queries are capped at 366 days (`ShowProgressRequest`) because the
> series materializes one `DailyWordCount` per day in PHP. Snapshots
> themselves are never pruned.
