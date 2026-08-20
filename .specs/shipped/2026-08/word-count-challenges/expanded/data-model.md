# Data model

## New table: `challenges`

```
id
project_id    foreignId, constrained, cascadeOnDelete
name          string
recurrence    string   default 'none'    # ChallengeRecurrence: none | monthly
starts_on     date
ends_on       date nullable                # required for `none`; optional stop date for `monthly`
target_words  unsignedInteger
timestamps
index(project_id, starts_on)
```

- **No progress columns.** Words so far, par, ahead/behind and the verdict all fall out of
  `word_count_snapshots`. A stored figure would be stale the moment a scene is saved, and
  editing old text would make it unreconcilable — the same argument that made snapshots
  cumulative.
- **`starts_on` / `ends_on` are `date`s**, matching `word_count_snapshots.recorded_on`. A
  timestamp would invite re-deriving the writer-day in the wrong zone. See `WriterDay`.
- **`ends_on` nullable, for `monthly` only.** A fixed challenge always has an end. A monthly
  one runs until deleted, *or* until an optional stop date — which is how a writer ends a
  recurring challenge without deleting the record of every month it ran. Enforced in the Form
  Requests, not by a check constraint; the project uses no check constraints today.
- **No unique key.** Overlapping challenges are a goal, so nothing to enforce.
- **`index(project_id, starts_on)`** — every read is "this project's challenges, newest
  window first". No second index; the table holds tens of rows per project.
- **No `HasRevisions`.** A challenge is a target, not authored content. A revision trail of
  a number the writer nudged twice is noise, and `RevisionBrowser` would list it beside scenes.
- No `user_id` — the owner is reachable through the project (same reasoning as snapshots).

`App\Models\Challenge` — `belongsTo(Project::class)`, `$casts = ['starts_on' => 'date',
'ends_on' => 'date', 'recurrence' => ChallengeRecurrence::class]`, `$fillable` = name,
recurrence, starts_on, ends_on, target_words.

`Project::challenges(): HasMany` — `orderByDesc('starts_on')`, because "the project's
challenges" means newest first everywhere it is asked for.

## Recurrence without occurrence rows

`App\Enums\ChallengeRecurrence`: `None`, `Monthly`.

A monthly challenge stores **one** row. Its windows are *derived*: the calendar month
containing the day being asked about, clipped to `starts_on`. Nothing materialises rows per
month.

- No scheduler, no `challenge_occurrences` table, no backfill when a writer creates a
  monthly challenge dated a year ago — the same "derive it, don't store it" rule as the
  daily deltas.
- Changing `target_words` re-scores every past month. That is the documented behaviour of
  the two project goals as well ("not historicized — changing one re-draws the past").

**Rejected: a nightly job that materialises next month's challenge.** It adds a scheduler
the app does not have, and it stores what a `startOfMonth()` call already knows.

## Export / import

New archive member `data/challenges.json`, a list of
`{name, recurrence, starts_on, ends_on, target_words}` — no ids; challenges reference nothing.

- Written by `StaticSiteExporter::addChallenges()`, beside `addWordCountSnapshots()`.
- Restored inside `ImportPhase::Project`, in `ProjectGraphImporter`, by the same
  `DB::table()->insert()` path the snapshots use (no model events, no re-derivation).
- `ArchiveValidator::LIST_ITEM_REQUIRED_KEYS` gains the file with
  `['name', 'recurrence', 'starts_on', 'ends_on', 'target_words']`.
- **Bump `StaticSiteExporter::DATA_VERSION` to 5.** The importer reads the file with
  `readJsonIfPresent`, so a version-4 archive imports with no challenges — absent is "none",
  not an error.

## Seeding

`Database\Seeders\Concerns\SeedsWordCountHistory` already invents 60 days of Melusine
history. Add `SeedsChallenges` beside it, creating two challenges against those same dates:

| Challenge | Window | Target |
| --- | --- | --- |
| finished | the calendar month that ended before today | a figure the generated history beat |
| running | the current calendar month, `monthly` | roughly the month's real pace |

Targets are computed from the generated plan, not hard-coded, so the demo shows a *met*
verdict and a plausible ahead/behind on any day the seeder runs. Same warning as the history
generator: fictional projects only.
