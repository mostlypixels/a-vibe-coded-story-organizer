# Testing

Style is `tests/Feature/ProjectTest.php`: plain PHPUnit, `RefreshDatabase`, factories,
`actingAs`, `route()`. Note `composer test` runs in parallel with one DB per process — no
test may assume another's rows.

## `WordCountSnapshotTest` — the write path

| Case | Assertion |
| --- | --- |
| Saving a scene's `contents` creates today's row | one row, `word_count` = project `SUM` |
| Saving twice the same day | still **one** row, updated — the unique key holds |
| Saving on a second day | two rows; yesterday's is untouched |
| Saving only `notes` / `status` | no row created — the `wasChanged('word_count')` guard |
| Deleting a scene | row updated downward |
| Deleting a **chapter** | row updated — proves the cascade does not lose the write |
| Deleting an **act** | row updated — the two-level cascade |
| Deleting an act **with children reassigned** | row unchanged — no words were lost |
| Reverting a revision (`RevisionReverter`) | row updated — the reason this is a hook |
| Seeder / `DB::table()` bulk write | **no** row — the documented non-recording path |

## `WriterDayTest` — the timezone

The bug this feature is most likely to ship. `travelTo()` a fixed UTC instant and vary the
owner's zone:

- Owner in `Pacific/Auckland`, save at `2026-08-08 13:00 UTC` → `recorded_on` is
  **2026-08-09**.
- Owner in `America/Los_Angeles`, same instant → **2026-08-08**.
- Owner with `timezone = null` → `config('app.timezone')`.
- Two saves either side of the owner's local midnight → **two** rows.
- The recorder uses the **owner's** zone, not the actor's.
- Changing the timezone leaves existing rows' `recorded_on` untouched.

Add a `UserFactory` state for the zone.

## `WordCountHistoryTest` — the read path

Pure arithmetic; the highest-value tests here.

- Consecutive days → `written` is each day's difference.
- A gap of three days → four entries, the gap days carry the previous total and `written` 0.
- **A range starting mid-history** → the first day's `written` uses the row *before* the
  range, not the range's own first row. Without this, every month starts with a false spike.
- **No row before the range at all** → the previous total is **0**, so a new project's first
  writing day is counted in full. This is the rule that replaced the old "first day writes
  nothing"; assert the full figure, not zero.
- A day where words dropped → `written` is negative.
- Empty range → empty series, no error.
- `writtenOn()` for a day with no row → 0.

## `ProgressPageTest`

- Owner `GET projects.progress` → 200, chart data present.
- Non-owner → **403** (from `ShowProgressRequest::authorize()`, not just the controller).
- No `from`/`to` → the current month **in the owner's zone** (assert under `travelTo` with a
  non-UTC owner).
- `from` after `to` → validation error.
- A span over 366 days → validation error.
- A project with no snapshots → the empty state, not a chart.
- The page appears under Tools in the nav.

## `ProjectTest` additions — goals and the dashboard card

- Owner sets both goals; they persist. Non-owner `PATCH` → 403.
- Goals accept empty (cleared to `null`), reject negatives and non-integers
  (`assertSessionHasErrors`).
- `projects.show` renders the Progress card with a 14-day series.
- A project with no goals renders the card without goal rows — no "of ∞", no divide by zero.

## `ProfileTest` additions

- Saving a valid identifier persists it; `""` stores `null`.
- `Europe/NotAPlace` → `assertSessionHasErrors('timezone')`.

## `ToolsHomeTest`

- The landing page lists a card per tool, linking to Revisions and Progress.
- Non-owner → 403.
- `SectionStubTest` no longer expects `stub` for Tools — update it as #89 did for the other
  three.

## Export / import round trip

- Export a project with goals and snapshots, import the archive, assert both survive and the
  restored project's series matches the original's.
- Import an archive **without** a snapshots section (pre-feature format) → succeeds, no rows.

## Query budget

`projects/show` is the heaviest page in the app and #89 added eight queries to it. Assert the
card costs **two** more — the 14-day range and the preceding row — not one per day.

## JS

`resources/js/word-count-chart.test.js` (vitest, co-located). The delta arithmetic lives in
PHP, so cover only what breaks:

- Mounting twice over the same canvas destroys the first instance (no *"Canvas is already in
  use"*).
- A `null` daily goal produces a one-dataset config, not a dataset of `null`s.
- `variant="compact"` omits axes and tooltips.

## `DatabaseSeederTest` additions — demo history

Two assertions only, both from [demo-history](demo-history.md):

- Every seeded project has the generator's exact set of dates (rest days are absent by
  design — assert against the generator's plan, not against `$days`).
- The **last** snapshot's `word_count` equals the live `$project->sceneQuery()->sum('word_count')`.

The generator is seeded deterministically, so both are exact numbers, not ranges.

## Browser check

Drive it with `/run-imagoldfish` before calling it done: set goals, save a scene, confirm
today's bar moves, switch the range, and confirm the chart follows a theme change. A chart
that silently renders in the wrong palette passes every test above.
