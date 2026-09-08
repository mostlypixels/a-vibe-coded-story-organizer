# Architecture

## The endpoint

A dedicated action, not `EventController::store()` with `wantsJson()`. `store()` requires
`plotlines` and redirects to the index; the inline path must attach the Main plotline
itself and answer with a row. Bending one action to both shapes costs more than a second
one.

```
POST  /projects/{project}/events/quick        -> QuickEventController::store
name: projects.events.quick-store
```

- Register it in `routes/web.php` **before** `Route::resource('projects.events', …)`, or
  `/quick` reads as an `{event}` segment.
- `StoreQuickEventRequest` in `app/Http/Requests`: `title` required, `event_datetime`
  required and `new WithinEventWindow($project)` — the same rule the parent forms use, so
  one window definition serves both paths.
- `authorize()` mirrors `ProjectPolicy::update`, like `StoreEventRequest`.
- The controller delegates to `CreatesInlineEvents::resolveInlineEvent()`. It already
  creates the event and attaches the Main plotline; do not re-implement that beside it.

Response, 201:

```json
{ "id": 42, "title": "The Second Curse", "datetime": "1215-04-02T00:00", "label": "The Second Curse — 2 April 1215" }
```

- `label` is rendered server-side by `DateFormat::date()` with the request locale. The
  client must not format dates — the existing options are built in Blade and the two
  formats would drift.
- `datetime` uses `EventWindow`'s `Y-m-d\TH:i`, so a caller can compare it against the
  field bounds without parsing.

Validation errors come back as Laravel's standard 422 envelope. No custom shape.

## The client

`resources/js/quick-event.js`, exporting one function, with `quick-event.test.js` beside
it — the pattern of `scene-reorder.js`. Posts with `window.axios`, which
`bootstrap.js` already arms with the CSRF token.

- On success, insert an `<option>` into **every** `select[data-event-picker]` on the page,
  ordered by `datetime` against the existing options' `data-datetime`, then select it in
  the picker that owns the button.
- On 422, write the messages under the two inputs.
- On any other failure, leave the inline inputs open and show one message. The parent form
  still carries the fields, so a failed quick-save degrades to today's behaviour.

`x-single-event-field` keeps its Alpine `x-data`; the button calls into the module.

## What stays

`resolveInlineEvent()` and the `new_*_title` / `new_*_datetime` form keys stay on
`SceneController`, `CodexEntrySaver` and their Form Requests. They are the no-JavaScript
path and the fallback, and removing them turns a progressive enhancement into a
requirement.
