# Testing

## Feature tests — `tests/Feature/QuickEventTest.php`

| Case | Expect |
|---|---|
| Owner posts a title and a datetime in the window | 201, event exists, Main plotline attached, JSON carries id/title/datetime/label |
| Response `label` | matches what the picker renders — `DateFormat::date()` in the request locale |
| Missing title | 422, no event created |
| Datetime outside `[Start, End]` | 422 with the `WithinEventWindow` message |
| Non-owner | 403 |
| Guest | redirect to login |
| The scene or codex entry it was created from | unchanged — this is the whole point |

Also assert the route resolves: `/projects/{p}/events/quick` must not be swallowed by
`Route::resource('projects.events')`'s `{event}` segment. A test hitting the URL with a
project that has an event id 1 catches the ordering mistake.

## JS tests — `resources/js/quick-event.test.js`

- Inserts the option into every `select[data-event-picker]`, once each.
- Orders the new option by `data-datetime`, not by append.
- Selects it only in the picker that owns the button.
- A 422 writes messages under the inputs and leaves the section open.
- A network failure leaves the typed values in place, so the parent-form path still works.

## Regression

`tests/Feature/SceneTest.php` and `tests/Feature/CodexEntryTest.php` already cover the
`new_event_title` form path (`CodexEntryTest` around the inception-event cases). They must
keep passing untouched — that path is the fallback, not dead weight.
