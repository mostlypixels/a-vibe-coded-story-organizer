# Data model

## New table: `word_count_snapshots`

```
id
project_id      foreignId, constrained, cascadeOnDelete
recorded_on     date            # the writer's local date, not a timestamp
word_count      unsignedInteger  default 0
timestamps
unique(project_id, recorded_on)
```

- **`recorded_on` is a `date`, not a `datetime`.** The row means "the total at the end of
  this writer-day". A timestamp would invite reading it as an instant and re-deriving the
  day in the wrong zone at every call site.
- **Column name, not `date`.** `date` is a reserved word on enough engines to be a
  liability, and `recorded_on` reads correctly next to `created_at`.
- **`unique(project_id, recorded_on)`** is both the invariant *and* the range index — a
  month query is `where project_id = ? and recorded_on between ? and ? order by
  recorded_on`, which the same composite serves left-to-right. No second index.
- **`unsignedInteger default 0`, never nullable** — same reasoning as `scenes.word_count`:
  0 is a real answer. A day the writer emptied the project stores 0, not `null`.
- Cumulative, never a delta. Deltas are derived (see [architecture](architecture.md)); a
  stored delta cannot be reconciled back to a total after an edit to old text.
- No `user_id`. The owner is reachable through the project, and duplicating it invites the
  two to disagree after a future project transfer.

`App\Models\WordCountSnapshot` — `belongsTo(Project::class)`, `$casts = ['recorded_on' =>
'date']`, no `HasRevisions` (a snapshot is not authored content), no factory state beyond
the default.

`Project::wordCountSnapshots(): HasMany` ordered by nothing — callers order.

## Changed: `projects`

```
daily_word_goal    unsignedInteger nullable
monthly_word_goal  unsignedInteger nullable
total_word_goal    unsignedInteger nullable
```

Nullable with no default: `null` means "no goal set" and the grey line / progress readout
is simply absent. A default of 0 would draw a goal line at zero on every project ever
created. Add all three to `Project::$fillable`.

Deliberately **not** a `WordCountSetting` side table. `PublicationSetting` earns its own
table by holding ~25 export-only columns; three integers edited on the project form belong
on the project, and a side table would add a `…OrDefault()` accessor for nothing.

## Changed: `users`

```
timezone  string nullable
```

`null` means "follow `config('app.timezone')`" — copy the `users.theme_slug` migration's
docblock reasoning verbatim: **do not** write the default into the column, or every
existing user is frozen onto today's default. Add `timezone` to `User::$fillable`.

A per-user column, not per-project: a writer keeps one working day across all their
projects, and a project has no opinion about what time it is.

## Seeding

`DatabaseSeeder` uses `WithoutModelEvents`, so no seeded project gets snapshots as a side
effect of being created. Each Melusine seeder therefore generates its own history
explicitly, the same way it already backfills scene word counts — see
[demo-history](demo-history.md).

`UserFactory` leaves `timezone` null. A factory state (`->inTimezone('Pacific/Auckland')`)
is worth adding for the timezone tests in [testing](testing.md).
