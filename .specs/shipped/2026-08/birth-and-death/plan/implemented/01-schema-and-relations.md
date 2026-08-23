# 01 — Schema and relations

## Scope

- Migration: add `inception_event_id`, `termination_event_id` to `codex_entries` — nullable FK to
  `events.id`, `nullOnDelete`. No extra index.
- `CodexEntry`: add both columns to `$fillable`; add `inceptionEvent()` and `terminationEvent()`
  `belongsTo(Event::class)`.
- Update `CodexEntryFactory` so the columns can be set in tests (default null).

Does **not**: add any computed method (`ageAt`/`existsAt`/`hasInvertedLifespan`), enum labels, the
`Age` object, controller/request/UI changes, or seeding. Those are tasks 02, 04, 05, 06.

## Depends on

Nothing.

## Key decisions

- Neutral column names; FK columns, not attributes (`open-questions.md` #2, #3).
- `nullOnDelete`, not cascade — a deleted event must not delete the entry.

## Consult

`expanded/data-model.md`.

## Tests

- A migration/relation test: set `inception_event_id`, reload, assert `inceptionEvent` resolves;
  same for termination.
- Delete a linked event → entry survives and the column is null (`nullOnDelete`).
