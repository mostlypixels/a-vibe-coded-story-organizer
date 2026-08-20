# Writing progress

[Documentation](../README.md) › [Features](README.md) › Writing progress

## Counting rule

`App\Support\WordCounter::count()` is the only server-side definition of a word.

1. Remove fenced code blocks from Markdown source.
2. Convert the value to plain text for its `FieldKind`.
3. Split on whitespace.
4. Remove tokens that contain no letter or digit.

| Input | Result |
| --- | --- |
| `one—two` | One word |
| `* * *` | Zero words |
| `**bold**` | One word |
| Fenced code | Zero words |
| Inline code | Counted |

Malformed UTF-8 returns zero instead of blocking a save.

## Stored invariant

Only `scenes.contents` has a stored count.

```text
scenes.word_count = WordCounter::count(contents, FieldKind::Markdown)
```

`Scene::saving` updates the count when contents change. This covers autosave, manual save, revert, undo, import, and normal model writes.

> [!WARNING]
> Seeders and bulk backfills bypass model events. They must use `BackfillsSceneWordCounts` or an equivalent bulk update. A model loop would create revision history and change timestamps.

Chapter, act, book, and project totals use `SUM`. Controllers calculate totals before rendering to prevent N+1 queries. Empty aggregates normalize to zero.

## Live counter

The browser counter is an estimate.

- `resources/js/word-count.js` splits on whitespace.
- Tiptap sends rendered text with `wysiwyg:text-changed`.
- Autosave returns the authoritative server count.
- The server result replaces the estimate and cancels pending recounts.

`App\Support\WordCountFormat` supplies the same pluralized templates to PHP and JavaScript.

## Writer day and snapshots

`App\Support\WriterDay` converts the project owner's timezone to a local date. Use the resource owner in background and import workflows.

`word_count_snapshots` stores one cumulative project total for each local day.

- The unique key is `(project_id, recorded_on)`.
- `WordCountSnapshotRecorder` uses an atomic upsert.
- `WordCountHistory` derives daily changes. It does not store them.
- The total before the first snapshot is zero.
- Snapshots cover all books in the project.
- A book deletion records a snapshot because database cascades do not fire scene deletion hooks.

Imports restore snapshot rows directly. Older archives without snapshot data import with no history.

## Goals and chart

Projects have nullable `daily_word_goal` and `total_word_goal` columns.

- The chart uses bars for daily changes and a dashed line for the daily goal.
- Negative changes use the danger token.
- The status strip shows current values, independent of the selected range.
- Current total words come from the live scene sum.
- Range queries are limited to 366 days. Snapshots are not pruned.

## Challenges

A challenge is a **window plus a target** (`name`, `starts_on`, `ends_on`, `target_words`).
Nothing about progress is stored — words so far, par, ahead/behind, and the finished verdict
are all arithmetic over `word_count_snapshots`, the same rows the chart above reads.

- `App\Models\Challenge` belongs to a project. Several may run at once, and their windows may
  overlap.
- `App\Enums\ChallengeRecurrence::Monthly` stores **one row**. Its window is the calendar
  month containing the day being asked about — derived at read time, never materialized into
  occurrence rows. A challenge started on the 10th is **not** pro-rated: it is behind par for
  that first month, same as any other month.
- An optional `ends_on` on a monthly challenge stops the recurrence without deleting the row,
  so every month it ran stays readable.
- A fixed (`None`) challenge always has an `ends_on` and caps at 366 days, matching the chart
  range cap. The cap does not apply to `Monthly`.
- A challenge is `Running` through its last day and `Finished` from the next day
  (`App\Enums\ChallengeState`).
- `written` is words written **inside the window** (`WordCountSeries::writtenInRange()`). A
  window opening before the project's first snapshot needs no special case — the total before
  that snapshot is already zero.
- **Par counts finished days only.** Day 1 opens at par 0, so a challenge is never behind
  before the writer has had a full day — the same forgiveness `currentStreak()` gives today.
  The par *line* on the chart still plots the end-of-day figure, so it reaches full par only
  once the challenge finishes.
- Negative words (a net cut) still display: an empty bar in the danger colour. Overshoot keeps
  counting past a full bar, reporting "target reached" instead of a per-day figure.
- Editing a target re-scores the past, the same as the two project goals: no revisions, no
  lock, no warning.
- `App\Services\ChallengeProgress` turns a `Challenge` and `WriterDay::for($project->user)`
  into an `App\Support\ChallengeStanding` — the view never calculates.

## Where things live

| Concern | Location |
| --- | --- |
| Count definition | `app/Support/WordCounter.php` |
| Formatting | `app/Support/WordCountFormat.php` |
| Stored invariant | `app/Models/Scene.php` |
| Live counter | `resources/js/word-count.js` |
| Local date | `app/Support/WriterDay.php` |
| Snapshot writes | `app/Services/WordCountSnapshotRecorder.php` |
| History reads | `app/Services/WordCountHistory.php` |
| Progress page | `app/Http/Controllers/ProgressController.php` |
| Chart | `resources/js/word-count-chart.js` |
| Challenge row | `app/Models/Challenge.php` |
| Recurrence, state | `app/Enums/ChallengeRecurrence.php`, `app/Enums/ChallengeState.php` |
| Window derivation | `app/Support/ChallengeWindow.php` |
| Standing arithmetic | `app/Services/ChallengeProgress.php` |
| Standing value object | `app/Support/ChallengeStanding.php` |
| Challenge CRUD | `app/Http/Controllers/ChallengeController.php` |
| Challenge chart | `resources/js/word-count-chart.js` (second component, not a chart variant) |

## Related documentation

- [Architecture](../architecture/README.md)
- [Rich text](rich-text.md)
- [Archive format](../export-import/archive-format.md)
