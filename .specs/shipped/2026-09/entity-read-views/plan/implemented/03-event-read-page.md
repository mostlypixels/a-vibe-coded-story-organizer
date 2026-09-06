# 03 — Event read page

## Scope

- `show` on the `projects.events` resource (`routes/web.php:138`) → `events.show`.
- New `app/Services/EventLifespanEntries.php` — codex entries this event starts or ends.
  There is no reverse relation for `inception_event_id` / `termination_event_id` today.
  One query, entries grouped by role:

  ```php
  /** @return array{inceptions: Collection<int, CodexEntry>, terminations: Collection<int, CodexEntry>} */
  public function forEvent(Event $event): array
  ```

- `EventController::show()`: authorize `view`, load `plotlines`, `scenes.chapter.act`,
  `mentioningScenes.chapter.act`, and the lifespan entries.
- `resources/views/events/show.blade.php`: title, datetime (`x-date`), fixed badge,
  description, plotline badges, scenes on the event, scenes mentioning it, and the codex
  entries it starts or ends.
- `resources/views/events/index.blade.php`: name link (line 35) → `events.show`; add
  `x-icon-view-link` (line 58).

## Depends on

Nothing.

## Key decisions

- No `CodexAsOfResolver`. The as-of panel is an editing surface; the read page does not
  resolve codex values as of the event.
- A fixed event offers no delete — mirror `EventController::destroy()`'s `is_fixed` guard.
- History slug is `event`.

## Consult

`expanded/architecture.md` → New service; `expanded/overview.md` → the content table.

## Tests

In `tests/Feature/EventTest.php`:

- Renders title, datetime, description, plotlines.
- Lists scenes on the event and scenes mentioning it, separately.
- `EventLifespanEntries`: an event that starts one entry and ends another puts each in its
  own group; an event with neither returns two empty collections.
- Omits every empty section.
- No form, input, or autosave field. Non-owner gets 403.
- Index links the name to `show`.
