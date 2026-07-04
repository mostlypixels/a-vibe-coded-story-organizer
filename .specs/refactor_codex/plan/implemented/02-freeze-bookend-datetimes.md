# 02 — Freeze bookend datetimes (finding 4)

## Scope

Make Start/End resolution stable by forbidding `event_datetime` changes on `is_fixed`
events. Title, description, and plotlines stay editable.

- `app/Http/Requests/UpdateEventRequest.php`: `event_datetime` becomes conditional —

  ```php
  'event_datetime' => $this->route('event')->is_fixed
      ? ['prohibited']
      : ['required', 'date'],
  ```

- `resources/views/events/edit.blade.php`: hide (or render read-only) the datetime input
  for fixed events, using the existing `@unless ($event->is_fixed)` pattern the view
  already uses for the delete affordance. Show the frozen date as plain text so the writer
  still sees it.
- `EventController@update`: verify the update payload for a fixed event simply lacks
  `event_datetime` (validated data won't contain a prohibited field) and does not null the
  column — adjust how validated data is applied only if needed.

Does **not** add an `is_start` column or any migration (binding decision Q1), and does
not change `AttributeTimeline` (task 03).

## Depends on

01 (the regression assertion uses `Project::startEvent()`).

## Key decisions already made

- Q1 resolved: freeze the datetime; no schema change. The bookend contract narrows from
  "editable but not deletable" to "datetime frozen" — task 09 updates the docs wording.
- `prohibited` (not silently ignoring the field): tampering the input back in must yield a
  visible validation error.

## Consult

`.specs/refactor_codex/timeline-integrity.md` (§ Finding 4), `open-questions.md` Q1.

## Tests

In `tests/Feature/ProjectTest.php` (where event coverage lives today):

- `test_fixed_event_datetime_cannot_be_changed`: owner PATCHes the Start event with a new
  `event_datetime` → `assertSessionHasErrors('event_datetime')`, datetime unchanged in DB.
  Fails before the fix.
- Fixed event title edit (without `event_datetime` in the payload) still succeeds.
- Non-fixed event datetime edit still succeeds (rule stays `required|date`).
- Regression: after the attempted edit, `Project::startEvent()` still resolves to the
  original Start event.
