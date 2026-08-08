# Testing

Style is `tests/Feature/ProjectTest.php`: plain PHPUnit, `RefreshDatabase`, factories,
`actingAs`, `route()`. Note `composer test` runs in parallel with one DB per process — no
test may assume another's rows.

## `WordCountSnapshotTest` — the write path

| Case | Assertion |
|---|---|
| Saving a scene's `contents` creates today's row | one row, `word_count` = project `SUM` |
| Saving twice the same day | still **one** row, updated — the unique key holds |
| Saving on a second day | two rows; yesterday's is untouched |
| Saving only `notes` / `status` | no row created — the `wasChanged('word_count')` guard |
| Deleting a scene | row updated downward; the day can be negative in *derived* terms |
| Deleting a **chapter** | row updated — proves the cascade does not lose the write |
| Deleting an **act** | row updated — the two-level cascade |
| Reverting a revision (`RevisionReverter`) | row updated — the reason this is a hook |
| Seeder / `DB::table()` bulk write | **no** row as a side effect — the documented non-recording path |
| Finishing a project import | **one** row, equal to the imported project's `SUM` |

## `DatabaseSeederTest` additions — demo history

Two assertions only, both from [demo-history](demo-history.md):

- Every seeded project has one snapshot per day across the generated range, no gaps in the
  row *sequence* (rest days are absent by design — assert the count of distinct dates
  against the generator's own rest-day plan, not against `$days`).
- The **last** snapshot's `word_count` equals the live `$project->sceneQuery()->sum('word_count')`.
  A chart whose final point disagrees with the page header discredits the feature.

The generator is seeded deterministically, so both assertions are exact numbers, not ranges.

## `WriterDayTest` — the timezone

The bug this feature is most likely to ship. `travelTo()` a fixed UTC instant and vary the
owner's zone:

- Owner in `Pacific/Auckland`, save at `2026-08-08 13:00 UTC` → `recorded_on` is
  **2026-08-09**.
- Owner in `America/Los_Angeles`, same instant → **2026-08-08**.
- Owner with `timezone = null` → `config('app.timezone')`.
- Two saves either side of the owner's local midnight → **two** rows.
- The recorder uses the **owner's** zone, not the actor's: act as a different user via an
  artisan-style path and assert the owner's date wins.

Add a `UserFactory` state for the zone.

## `WordCountHistoryTest` — the read path

Pure arithmetic; the highest-value tests here.

- Consecutive days → `written` is each day's difference.
- A gap of three days → four entries, the gap days carry the previous total and `written` 0.
- **A range starting mid-history** → the first day's `written` uses the row *before* the
  range, not the range's own first row. Without this, every month starts with a false spike.
- **No row before the range at all** → first day's `written` is 0, not its whole total.
- A day where words dropped → `written` is negative.
- Empty range → empty series, no error, no division by anything.

## `ProjectTest` additions — goals and range

- Owner sets the three goals; they persist. Non-owner `PATCH` → 403.
- Goals accept `null`/empty (cleared), reject negatives and non-integers
  (`assertSessionHasErrors`).
- `GET projects.show` with no `from`/`to` → the current month **in the owner's zone**
  (assert under `travelTo` with a non-UTC owner).
- `from` after `to` → validation error. A span over the cap → validation error.
- Non-owner `GET projects.show?from=…` → 403 (the Form Request's `authorize()`, not just
  the controller's).

## `ProfileTest` additions

- Saving a valid identifier persists it; `""` stores `null`.
- `Europe/NotAPlace` → `assertSessionHasErrors('timezone')`.

## Query budget

`ProjectController::show()` is already the app's heaviest page. Assert with
`DB::listen` (or an existing helper, if one is in use) that adding the chart costs
**one** extra query for the range plus **one** for the preceding row — not one per day.

## JS

`resources/js/word-count-chart.test.js` (vitest, co-located). The delta arithmetic lives in
PHP, so the module has little logic worth testing — cover the two things that break:

- Mounting twice over the same canvas destroys the first instance (no *"Canvas is already
  in use"*).
- A `null` daily goal produces a one-dataset config, not a dataset of `null`s.

## Browser check

Drive it with `/run-imagoldfish` before calling it done: set goals, save a scene, confirm
today's point moves, switch the range, and confirm the chart follows a theme change. A chart
that silently renders in the wrong palette passes every test above.
