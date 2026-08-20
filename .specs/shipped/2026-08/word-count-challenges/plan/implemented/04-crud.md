# 04 — Create, edit, delete

## Scope

- `Route::resource('projects.challenges', ChallengeController::class)->only(['create',
  'store', 'edit', 'update', 'destroy'])->shallow()`, beside `projects.plotlines`.
- `ChallengeController`, following `PlotlineController`.
- `StoreChallengeRequest` / `UpdateChallengeRequest`.
- `resources/views/challenges/create.blade.php` and `edit.blade.php`.

**Not** in this task: the Progress page listing (05) or the chart (06). Redirects target
`projects.progress`, which already exists — it just shows nothing about challenges yet.

## Depends on

01. (Independent of 02/03; the forms show no progress.)

## Key decisions

- **No `index` action.** The Progress page is the list.
- Rules: `expanded/architecture.md` → *Validation*. The 366-day cap applies to `none` only.
- `ends_on` is optional for `monthly` and means "stop after that month". Do **not** null it
  when the recurrence is monthly.
- No `RecordsManualRevisions`, no rich text, no autosave.
- The end-date row hides under Alpine for a monthly challenge, with help text; the server rule
  is the real one.
- A live "about N words a day" hint under the target, computed in Alpine from the three
  fields — see `expanded/ui.md` → *Create / edit form*.
- Delete lives on the Progress card (05), not on the form; `destroy` must still work here.

## Consult

`expanded/architecture.md` → *Routes and controllers*, *Validation*; `expanded/ui.md` →
*Create / edit form*.

## Tests

`tests/Feature/ChallengeTest.php`, following `PlotlineTest`:

- create / update / delete round trip, redirecting to `projects.progress`;
- 403 for a non-owner on all five routes;
- validation: `ends_on` before `starts_on`, a `none` challenge over 366 days, a `none`
  challenge with no `ends_on`, `target_words` of 0;
- a monthly challenge saves with an empty `ends_on`, and saves with one set;
- a monthly challenge over 366 days long is accepted.
