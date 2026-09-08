---
title: "Task 01 — Quick event endpoint"
---

# Task 01 — Quick event endpoint

## Scope

The server half: one JSON endpoint that creates a regular event on the Main plotline and
answers with the row the picker needs.

Does **not** touch any Blade or JavaScript — task 02 owns the button, the client module and
the option injection.

## Depends on

Nothing.

## Key decisions already made

* `CreatesInlineEvents::resolveInlineEvent(): ?int` becomes `createInlineEvent(): ?Event`,
  dropping its `$existingId` argument. Its four call sites (`SceneController::store`/`update`,
  `CodexEntrySaver::update` ×2) become `?->id ?? $validated[<key>] ?? null`. Behaviour there
  is unchanged, and no call site gains a query.
* Route: `POST /projects/{project}/events/quick`, name `projects.events.quick-store`,
  registered **before** `Route::resource('projects.events', …)` in `routes/web.php`.
* `StoreQuickEventRequest`: `title` required/string/max:255, `event_datetime` required/date
  + `new WithinEventWindow($project)`. `authorize()` mirrors `ProjectPolicy::update`.
* 201 body: `{ id, title, datetime, label }`. `datetime` in `EventWindow`'s `Y-m-d\TH:i`;
  `label` from `DateFormat::date()` with the request locale, matching what the Blade
  `<option>` renders today (`title &mdash; date`).
* Validation failures use Laravel's standard 422 envelope. No custom shape.

Detail: `expanded/architecture.md` → *The endpoint*.

## Tests to add

`tests/Feature/QuickEventTest.php`:

* Owner posts a valid title + datetime → 201, event exists, Main plotline attached, JSON
  carries all four keys.
* `label` equals what the picker renders for that event.
* Missing title → 422, nothing created.
* Datetime outside `[Start, End]` → 422 with the `WithinEventWindow` message.
* Non-owner → 403. Guest → redirect to login.
* The scene / codex entry the request came from is unchanged.
* Route resolution: the URL still reaches the quick action on a project that owns an event
  with id 1 — catches the resource-ordering mistake.

`tests/Feature/SceneTest.php` and `CodexEntryTest.php` must pass untouched; they cover the
`new_event_title` fallback.
