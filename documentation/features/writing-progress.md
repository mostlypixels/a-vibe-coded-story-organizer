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

## Related documentation

- [Architecture](../architecture/README.md)
- [Rich text](rich-text.md)
- [Archive format](../export-import/archive-format.md)
